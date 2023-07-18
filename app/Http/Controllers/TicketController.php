<?php

namespace App\Http\Controllers;

use App\Http\Controllers\CRM\UserController;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\TicketResource;
use App\Models\Message;
use App\Models\Ticket;
use App\Traits\TicketTrait;
use App\Traits\UserTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    $id = intval(htmlspecialchars(trim(request('id'))));
    $limit = intval(htmlspecialchars(trim(request('limit'))));
    $active = htmlspecialchars(trim(request('active')));
    $is_active = boolval($active);

    $data = \Illuminate\Support\Facades\DB::table('tickets')
      ->join('reasons', 'reasons.id', 'tickets.reason_id')
      ->when(!empty($active), function ($q) use ($is_active) {
        $q->whereActive($is_active);
      })
      ->when(!empty($id), function ($q) use ($id) {
        $q->whereId($id);
      })
      ->select('tickets.*', 'reasons.name AS reason')
      ->paginate($limit < 1 ? 100 : $limit);

    $search = UserTrait::search();
    $users_collection = array();

    foreach ($search->data as $user) {
      $users_collection[$user->crm_id] = $user;
    }
    unset($search);

    foreach ($data as $ticket) {
      $ticket->user = $users_collection[$ticket->user_id];
      $ticket->manager = $users_collection[$ticket->manager_id];
    }

    return response()->json([
      'status' => true,
      'data' => TicketResource::collection($data)->response()->getData()
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(StoreTicketRequest $request)
  {
    // Сделать проверку на наличие тем и ответственных (с группами)




    $data = $request->validated();
    $reason = TicketTrait::GetReason($data['message']);




    // Определить reason_id по reason
    // Для этого надо синхронизировать названия тем
    $reason_id = 1;




    $data['reason_id'] = $reason_id;
    $managers = TicketTrait::GetManagersForReason($reason_id);

    if (count($managers) > 1) {
      $manager_id = TicketTrait::SelectResponsive($managers);
      $current_manager = $managers->where('id', $manager_id)->first();
    } else {
      $current_manager = $managers->first();
    }

    $data['manager_id'] = $current_manager->crm_id;
    $data['weight'] = $current_manager->weight;
    // $reason = 'Новый тикет';//$reason;
    // TicketTrait::SendNotification(5 ?? $current_manager->crm_id, $data['name']);

    $user_id = Auth::user()->crm_id;
    $data['user_id'] = $user_id;
    $result = Ticket::create($data);
    $message = Message::create([
      'content' => $data['message'],
      'user_id' => $user_id,
      'ticket_id' => $result->id,
    ]);
    $result->reason = $reason;


    $search = UserTrait::search();
    $users_collection = array();

    foreach ($search->data as $u) {
      $users_collection[$u->crm_id] = $u;
    }
    unset($search);

    $result['user'] = $users_collection[$user_id];
    $result['manager'] = $users_collection[$current_manager->crm_id];

    return response()->json([
      'status' => true,
      'data' => TicketResource::make($result),
      'new_message' => MessageResource::make($message),
      'message' => 'Тикет успешно создан'
    ]);
  }

  /**
   * Display the specified resource.
   */
  public function show(Ticket $ticket)
  {
    //
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(UpdateTicketRequest $request, $id)
  {
    $data = Ticket::findOrFail($id);
    $data->fill($request->safe()->except(['id']));
    $data->save();

    Log::info("Ticket #" . $id . " has been updated");

    return response()->json([
      'status' => true,
      'data' => TicketResource::make($data)
    ]);
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy($id)
  {
    //
  }
}
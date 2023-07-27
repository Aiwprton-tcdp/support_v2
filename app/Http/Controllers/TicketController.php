<?php

namespace App\Http\Controllers;

use App\Http\Controllers\CRM\UserController;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\TicketResource;
use App\Models\Message;
use App\Models\Ticket;
use App\Traits\BX;
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
    $show_all = htmlspecialchars(trim(request('show_all')));
    $is_active = boolval($active);
    $is_show_all = boolval($show_all);

    // dd($active, $show_all, $is_active, $is_show_all);
    $data = \Illuminate\Support\Facades\DB::table('tickets')
      ->join('reasons', 'reasons.id', 'tickets.reason_id')
      ->when(!empty($active), function ($q) use ($is_active) {
        $q->whereActive($is_active);
      })
      ->when(empty($show_all) || !$is_show_all, function ($q) {
        $q->whereManagerId(Auth::user()->crm_id)
          ->orWhere('user_id', Auth::user()->crm_id);
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

    $checksum = \App\Traits\ReasonTrait::Checksum();
    BX::getDataE();
    $is_admin = BX::call('user.admin')['result'];
    $message = \App\Models\Manager::whereCrmId(Auth::user())->exists() || $is_admin
      ? 'Не созданы некоторые темы. Перейдите во вкладку "Темы" и заполните недостающие темы'
      : 'В настройке приложения допущены критические ошибки, обратитесь к администратору';

    return response()->json([
      'status' => count($checksum) == 0,
      'checksum' => $checksum,
      'data' => TicketResource::collection($data)->response()->getData(),
      'message' => $message
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(StoreTicketRequest $request)
  {
    $data = $request->validated();
    $reason = TicketTrait::GetReason($data['message'])
      ?? \App\Models\Reason::first();

    if ($reason == null) {
      return response()->json([
        'status' => false,
        'data' => $reason,
        'message' => 'В настройке приложения допущены критические ошибки, обратитесь к администратору',
      ]);
    }

    $reason_id = $reason->id;
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
    $user_crm_id = Auth::user()->crm_id;
    $data['user_id'] = $user_crm_id;

    $result = Ticket::create($data);
    Message::create([
      'content' => $data['message'],
      'user_crm_id' => $user_crm_id,
      'ticket_id' => $result->id,
    ]);

    $search = UserTrait::search();
    $users_collection = array();

    foreach ($search->data as $u) {
      $users_collection[$u->crm_id] = $u;
    }
    unset($search);

    $result['reason'] = $reason->name;
    $result['user'] = $users_collection[$user_crm_id];
    $result['manager'] = $users_collection[$current_manager->crm_id];

    // TicketTrait::SendNotification(5 ?? $current_manager->crm_id, $data['message']);

    return response()->json([
      'status' => true,
      'data' => TicketResource::make($result),
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
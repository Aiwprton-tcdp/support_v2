<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Message;
use App\Models\Ticket;
use App\Traits\TicketTrait;
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
      ->when(!empty($active), function ($q) use ($is_active) {
        $q->whereActive($is_active);
      })
      ->when(!empty($id), function ($q) use ($id) {
        $q->whereId($id);
      })
      ->paginate($limit < 1 ? 100 : $limit);

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
    $validated = $request->validated();

    $reason_id = 1;
    $validated['reason_id'] = $reason_id;

    return response()->json([
      'status' => true,
      'data' => ['name' => 'Менеджер тикета'],
      'message' => '(Тест) тикет успешно создан'
    ]);


    // Тестовые данные
    // $validated['reason_id'] = $reason_id;
    // $validated['weight'] = 5;
    // $validated['manager_id'] = 2;
    // $data = Ticket::create($validated);
    // $message = Message::create([
    //     'content' => $validated['message'],
    //     'user_id' => 2,
    //     'ticket_id' => $data ->id,
    // ]);

    // return response()->json([
    //     'status' => true,
    //     'data' => TicketResource::make($data),
    //     'message' => $message,
    // ]);
    // Тестовые данные



    $managers = \App\Models\Reason::join('groups', 'groups.id', 'reasons.group_id')
      ->join('manager_groups', 'manager_groups.group_id', 'groups.id')
      ->join('users', 'users.id', 'manager_groups.user_id')
      ->where('reasons.id', $reason_id)
      ->select('users.id', 'users.name', 'reasons.weight', 'reasons.name AS reason')
      ->groupBy('reasons.id', 'groups.id', 'manager_groups.id', 'users.id')->get();

    $manager_id = count($managers) > 1
      ? TicketTrait::selectResponsive($managers)
      : $managers[0]->id;
    
    $validated['manager_id'] = $manager_id;
    $weight = $managers->where('id', $manager_id)->first()->weight;
    $validated['weight'] = $weight;

    return response()->json([
      'status' => true,
      'data' => $manager_id,
      'message' => '(Тест) тикет успешно создан'
    ]);

    $user_id = 1; // Айди сотрудника из CRM
    $validated['user_id'] = $user_id;
    $data = Ticket::create($validated);

    return response()->json([
      'status' => true,
      'data' => TicketResource::make($data),
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

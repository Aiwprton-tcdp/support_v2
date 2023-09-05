<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\HiddenChatMessage;
use App\Models\Message;
use App\Models\Ticket;
use App\Traits\TicketTrait;
use App\Traits\UserTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    $search = htmlspecialchars(trim(request('search')));
    $limit = intval(htmlspecialchars(trim(request('limit'))));
    $show_all = htmlspecialchars(trim(request('show_all')));
    $is_show_all = boolval($show_all);

    $id = empty($search) ? 0 : intval(trim(preg_replace('/[^0-9]+/', '', $search)));
    $name = empty($search) ? null : mb_strtolower(trim(preg_replace('/[^А-яA-z ]+/iu', '', $search)));

    $user_crm_id = Auth::user()->crm_id;

    // DB::enableQueryLog();
    $data = DB::table('tickets')
      ->join('reasons', 'reasons.id', 'tickets.reason_id')
      ->rightJoin('users AS u', 'u.crm_id', 'tickets.manager_id')
      ->rightJoin('users AS m', 'm.crm_id', 'tickets.manager_id')
      ->leftJoin(
        'participants',
        fn($q) => $q->on('participants.ticket_id', 'tickets.id')
          ->where('participants.user_crm_id', $user_crm_id)
      )
      ->leftJoin(
        'messages',
        fn($q) => $q->on('messages.ticket_id', 'tickets.id')
          ->whereRaw('messages.id IN (SELECT MAX(m.id) FROM messages m join tickets t on t.id = m.ticket_id GROUP BY t.id)')
      )
      ->leftJoin(
        'hidden_chat_messages',
        fn($q) => $q->on('hidden_chat_messages.ticket_id', 'tickets.id')
          ->whereNotIn('hidden_chat_messages.user_crm_id', [0, 1])
          ->whereRaw('hidden_chat_messages.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id GROUP BY t.id)')
      )
      ->where('tickets.active', true)
      ->where(
        fn($r) => $r->where('tickets.manager_id', $user_crm_id)
          ->orWhere('tickets.user_id', $user_crm_id)
          ->orWhere('participants.user_crm_id', $user_crm_id)
      )
      ->when($id > 0, fn($r) => $r->where('tickets.id', $id))
      // ->where(function ($t) use ($id, $name) {
      // $t
      // ->when(!empty($search), function ($q) use ($search) {
      // $q
      ->when(isset($name), function ($y) use ($name) {
        $y->where(function ($s) use ($name) {
          $s->whereRaw('LOWER(u.name) LIKE ?', ["%{$name}%"])
            ->orWhereRaw('LOWER(m.name) LIKE ?', ["%{$name}%"]);
        });
        // });
        // });
      })
      // ->whereNotNull('tickets.id')
      ->select(
        'tickets.*',
        'tickets.id AS tid',
        'reasons.name AS reason',
        'messages.user_crm_id AS last_message_crm_id',
        'messages.created_at AS last_message_date',
        'hidden_chat_messages.created_at AS last_system_message_date'
      )

      ->orderBy(DB::raw("CASE WHEN messages.user_crm_id != {$user_crm_id} THEN 1 WHEN messages.user_crm_id = {$user_crm_id} THEN 3 ELSE 2 END"))
      ->orderByDesc('tickets.weight')
      ->orderBy('last_message_date')
      ->orderBy('tid')
      ->paginate($limit < 1 ? 100 : $limit);

    // dd(DB::getQueryLog());
    // Подсчёт количества новых сообщений

    // ->select('tickets.*', 'tickets.id AS tid', 'reasons.name AS reason',// 'messages.content AS last_message',
    //   DB::raw("SELECT COUNT(*)
    //   FROM (SELECT m.id, (SELECT MAX(m1.id) FROM messages m1 WHERE m1.ticket_id = tid AND m1.user_crm_id = {$user_crm_id}) AS max_id
    //   FROM messages m
    //   WHERE m.ticket_id = tid AND m.user_crm_id != {$user_crm_id}
    //   HAVING m.id > max_id) AS new_messages"))

    $search = UserTrait::search();
    $users_collection = array();

    foreach ($search->data as $user) {
      $users_collection[$user->crm_id] = $user;
    }
    unset($search);

    foreach ($data as $ticket) {
      $ticket->user = $users_collection[$ticket->user_id]
        ?? ['name' => 'Неопределённый пользователь'];
      $ticket->manager = $users_collection[$ticket->manager_id]
        ?? ['name' => 'Неопределённый менеджер'];
    }
    unset($users_collection);

    // BX::getDataE();
    // $is_admin = BX::call('user.admin')['result'];
    // dd($is_admin, BX::call('user.current')['result']);
    // $message = 'Не созданы некоторые темы. Перейдите во вкладку "Темы" и заполните недостающие темы';

    $message = \App\Models\Manager::whereCrmId($user_crm_id)->exists() // || $is_admin
      ? 'Не созданы некоторые темы. Перейдите во вкладку "Темы" и заполните недостающие темы'
      : 'В настройке приложения допущены критические ошибки, обратитесь к администратору';
    $checksum = \App\Traits\ReasonTrait::Checksum();

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
    if (in_array('Студент', explode(' ', Auth::user()->name))) {
      return response()->json([
        'status' => false,
        'data' => null,
        'message' => 'Студенческий аккаунт не имеет возможности создавать тикеты'
      ]);
    }

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

    if (!isset($managers)) {
      return response()->json([
        'status' => false,
        'data' => $reason,
        'message' => 'Нет ни одного менеджера, способного принять тикет',
      ]);
    }

    $current_manager = [];
    if (count($managers) > 1) {
      $id = TicketTrait::SelectResponsiveId($managers);
      // dd($id, $managers);
      if ($id > 0) {
        $current_manager = array_filter($managers, fn($m) => $m['crm_id'] == $id);
      }
      // $current_manager = $id > 0
      //   ? array_filter($managers, fn($m) => $m['crm_id'] == $id)
      //   : $managers[array_key_first($managers)];
      // $current_manager = $managers->when($id > 0, fn($m) => $m->where('crm_id', $id))->first();
    // } else {
    //   $current_manager = $managers[array_key_first($managers)];
    //   // $current_manager = $managers->first();
    }
    $current_manager = $current_manager[array_key_first($current_manager)];
    // dd($current_manager);
    $manager_id = $current_manager->crm_id;

    $data['manager_id'] = $manager_id;
    $data['weight'] = $current_manager->weight;
    $user_crm_id = Auth::user()->crm_id;
    $data['user_id'] = $user_crm_id;

    $ticket = Ticket::create($data);
    Message::create([
      'content' => $data['message'],
      'user_crm_id' => $user_crm_id,
      'ticket_id' => $ticket->id,
    ]);

    $ticket->reason = $reason->name;
    $ticket->user = UserTrait::tryToDefineUserEverywhere($user_crm_id);
    $ticket->manager = UserTrait::tryToDefineUserEverywhere($manager_id);

    $resource = TicketResource::make($ticket);
    TicketTrait::SendMessageToWebsocket("{$manager_id}.ticket", [
      'ticket' => $resource,
    ]);
    $message = "Новый тикет №{$ticket->id}\nТема: {$ticket->reason}\nСоздатель: {$ticket->user->name}";
    TicketTrait::SendNotification($manager_id, $message, $ticket->id);
    HiddenChatMessage::create([
      'content' => 'Тикет создан',
      'user_crm_id' => 0,
      'ticket_id' => $ticket->id,
    ]);
    Log::info($message);

    return response()->json([
      'status' => true,
      'data' => $resource,
      'message' => 'Тикет успешно создан'
    ]);
  }

  /**
   * Display the specified resource.
   */
  public function show($id)
  {
    $ticket = Ticket::join('reasons', 'reasons.id', 'tickets.reason_id')
      ->where('tickets.id', $id)
      ->select('tickets.*', 'reasons.name AS reason')
      ->first();

    if (!isset($ticket)) {
      return response()->json([
        'status' => false,
        'data' => null,
        'message' => 'Тикет не найден'
      ]);
    }

    $ticket->user = UserTrait::tryToDefineUserEverywhere($ticket->user_id);
    $ticket->manager = UserTrait::tryToDefineUserEverywhere($ticket->manager_id);

    return response()->json([
      'status' => true,
      'data' => TicketResource::make($ticket)->response()->getData()
    ]);
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(UpdateTicketRequest $request, $id)
  {
    $validated = $request->validated();
    $ticket = Ticket::join('reasons', 'reasons.id', 'tickets.reason_id')
      ->where('tickets.id', $id)
      ->select('tickets.*', 'reasons.name AS reason')
      ->first();

    if (!isset($ticket)) {
      return response()->json([
        'status' => false,
        'data' => $ticket,
        'message' => 'Тикет уже завершён'
      ]);
    }

    $user = UserTrait::tryToDefineUserEverywhere($ticket->user_id);
    $manager = UserTrait::tryToDefineUserEverywhere($ticket->manager_id);

    if (isset($validated['active'])) {
      if ($validated['active'] == false) {
        $name = Auth::user()->name;
        HiddenChatMessage::create([
          'content' => "{$name} пометил тикет как решённый",
          'user_crm_id' => 0,
          'ticket_id' => $ticket->id,
        ]);

        $message = "Тикет №{$ticket->id} был помечен менеджером как решённый\nПожалуйста, оцените работу менеджера";
        TicketTrait::SendMessageToWebsocket("{$ticket->user_id}.ticket.delete", [
          'id' => $ticket->id,
          'message' => null,
          'finished' => true,
        ]);
        TicketTrait::SendNotification($ticket->user_id, $message, $ticket->id);
      } else {
        $ticket_data = clone ($ticket);
        $ticket_data->active = 1;
        $ticket_data->user = $user;
        $ticket_data->manager = $manager;
        $message = "Тикет №{$ticket_data->id} был возвращён в работу";

        TicketTrait::SendMessageToWebsocket("{$ticket_data->manager_id}.ticket", [
          'ticket' => TicketResource::make($ticket_data),
        ]);
        TicketTrait::SendNotification($ticket_data->manager_id, $message, $ticket_data->id);
        HiddenChatMessage::create([
          'content' => 'Тикет возвращён в работу',
          'user_crm_id' => 0,
          'ticket_id' => $ticket_data->id,
        ]);
        unset($ticket_data);
      }
    }

    if (isset($validated['reason_id'])) {
      $reason = \App\Models\Reason::firstWhere('id', $validated['reason_id'])
        ?? \App\Models\Reason::find(1);
      $validated['reason_id'] = $reason->id;
      $validated['weight'] = $reason->weight;
    }

    $ticket->fill($validated);
    $ticket->save();

    $ticket = Ticket::join('reasons', 'reasons.id', 'tickets.reason_id')
      ->where('tickets.id', $id)
      ->select('tickets.*', 'reasons.name AS reason')
      ->first();
    $ticket->user = $user;
    $ticket->manager = $manager;

    if (!isset($validated['active'])) {
      $another_recipients = \App\Models\Participant::whereTicketId($ticket->id)
        ->whereNot('user_crm_id', Auth::user()->crm_id)
        ->get('user_crm_id');
      foreach ($another_recipients as $rec) {
        $id = $rec->user_crm_id;
        TicketTrait::SendMessageToWebsocket("{$id}.ticket.patch", [
          'ticket' => $ticket,
        ]);
      }
      foreach ([$ticket->user_id, $ticket->manager_id] as $id) {
        TicketTrait::SendMessageToWebsocket("{$id}.ticket.patch", [
          'ticket' => $ticket,
        ]);
      }
    }

    if (isset($validated['reason_id'])) {
      $name = Auth::user()->name;
      HiddenChatMessage::create([
        'content' => "{$name} изменил тему на {$ticket->reason}",
        'user_crm_id' => 0,
        'ticket_id' => $ticket->id,
      ]);
    }

    $message = "Тикет №{$ticket->id} ";
    if (isset($validated['active']) && $validated['active'] == true) {
      $message .= "возобновлён";
    } else {
      $message .= "успешно изменён";
    }
    Log::info($message);

    return response()->json([
      'status' => true,
      'data' => TicketResource::make($ticket),
      'message' => $message
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
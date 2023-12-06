<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParticipantRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\CheckedInstruction;
use App\Models\HiddenChatMessage;
use App\Models\Message;
use App\Models\Reason;
use App\Models\Ticket;
use App\Models\User;
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
    $search = $this->prepare(request('search'));
    $limit = intval($this->prepare(request('limit')));
    $pinned = $this->prepare(request('pinned'));
    // dd($pinned);

    $id = empty($search) ? 0 : intval(trim(preg_replace('/[^0-9]+/', '', $search)));
    $name = empty($search) ? null : mb_strtolower(trim(preg_replace('/[^А-яA-z ]+/iu', '', $search)));

    // dd($search, $id, $name, isset($name));
    $user_id = Auth::user()->id;

    // DB::enableQueryLog();
    $data = DB::table('tickets')
      ->join('reasons', 'reasons.id', 'tickets.reason_id')
      ->leftJoin('users AS u', 'u.id', 'tickets.new_user_id')
      ->leftJoin('users AS m', 'm.id', 'tickets.new_manager_id')
      // ->leftJoin(
      //   'bx_users AS bx',
      //   fn($q) => $q->on('bx.user_id', 'u.id')
      //       ->whereRaw('bx.id IN (SELECT MIN(id) FROM bx_users WHERE bx_users.user_id = u.id)')
      // )
      // // ->leftJoin('bx_users AS bx', 'bx.user_id', 'u.id')
      // ->leftJoin('bx_crms AS bxc', 'bxc.id', 'bx.bx_crm_id')
      ->leftJoin('bx_crms AS bxc', 'bxc.id', 'tickets.crm_id')
      ->leftJoin(
        'participants',
        fn($q) => $q->on('participants.ticket_id', 'tickets.id')
          ->where('participants.user_id', $user_id)
      )
      ->leftJoin(
        'messages',
        fn($q) => $q->on('messages.ticket_id', 'tickets.id')
          ->whereRaw('messages.id IN (SELECT MAX(m.id) FROM messages m join tickets t on t.id = m.ticket_id GROUP BY t.id)')
      )
      ->leftJoin(
        'hidden_chat_messages',
        fn($q) => $q->on('hidden_chat_messages.ticket_id', 'tickets.id')
          ->whereNotIn('hidden_chat_messages.new_user_id', [0, 1])
          ->whereRaw('hidden_chat_messages.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id GROUP BY t.id)')
      )
      // ->where('tickets.active', true)
      ->where(
        fn($q) => $q->where('tickets.active', true)
          ->orWhere('tickets.new_user_id', $user_id)->where('tickets.active', false)
      )
      ->where(
        fn($q) => $q->where('tickets.new_manager_id', $user_id)
          ->orWhere('tickets.new_user_id', $user_id)
          ->orWhere('participants.user_id', $user_id)
      )
      ->when($id > 0, fn($q) => $q->where('tickets.id', $id))
      ->when(
        isset($name) && $id == 0,
        fn($q) => $q->where(
          fn($r) => $r->whereRaw(
            "LOWER(u.name) LIKE ? OR LOWER(m.name) LIKE ? OR LOWER(reasons.name) LIKE ?",
            ["%{$name}%", "%{$name}%", "%{$name}%"]
          )
        )
      )
      ->whereNotNull('tickets.id')
      ->select(
        'tickets.*',
        'tickets.id AS tid',
        'reasons.name AS reason',
        'reasons.call_required AS important_reason',
        'messages.new_user_id AS last_message_user_id',
        'messages.created_at AS last_message_date',
        'hidden_chat_messages.created_at AS last_system_message_date',
        'bxc.name AS bx_name',
        'bxc.acronym AS bx_acronym',
        'bxc.domain AS bx_domain',
      )
      ->orderBy(DB::raw("CASE WHEN (tickets.new_user_id = {$user_id} OR tickets.new_manager_id = {$user_id}) THEN 1 ELSE 2 END"))
      ->orderBy(DB::raw("CASE WHEN messages.new_user_id != {$user_id} THEN 1 WHEN messages.new_user_id = {$user_id} THEN 3 ELSE 2 END"))
      ->orderByDesc('tickets.weight')
      ->orderBy('last_message_date')
      ->orderBy('tid')
      ->paginate($limit < 1 ? 100 : $limit);
    // dd($data);
    // dd(DB::getQueryLog());

    // Подсчёт количества новых сообщений
    // ->select('tickets.*', 'tickets.id AS tid', 'reasons.name AS reason',// 'messages.content AS last_message',
    //   DB::raw("SELECT COUNT(*)
    //   FROM (SELECT m.id, (SELECT MAX(m1.id) FROM messages m1 WHERE m1.ticket_id = tid AND m1.user_crm_id = {$user_id}) AS max_id
    //   FROM messages m
    //   WHERE m.ticket_id = tid AND m.user_crm_id != {$user_id}
    //   HAVING m.id > max_id) AS new_messages"))

    $pinnedData = [];
    if ($data->currentPage() == 1 && !empty($pinned)) {
      $pinnedData = DB::table('tickets')
        ->join('reasons', 'reasons.id', 'tickets.reason_id')
        ->leftJoin('users AS u', 'u.id', 'tickets.new_user_id')
        ->leftJoin('users AS m', 'm.id', 'tickets.new_manager_id')
        ->leftJoin('bx_crms AS bxc', 'bxc.id', 'tickets.crm_id')
        ->leftJoin(
          'participants',
          fn($q) => $q->on('participants.ticket_id', 'tickets.id')
            ->where('participants.user_id', $user_id)
        )
        ->leftJoin(
          'messages',
          fn($q) => $q->on('messages.ticket_id', 'tickets.id')
            ->whereRaw('messages.id IN (SELECT MAX(m.id) FROM messages m join tickets t on t.id = m.ticket_id GROUP BY t.id)')
        )
        ->leftJoin(
          'hidden_chat_messages',
          fn($q) => $q->on('hidden_chat_messages.ticket_id', 'tickets.id')
            ->whereNotIn('hidden_chat_messages.new_user_id', [0, 1])
            ->whereRaw('hidden_chat_messages.id IN (SELECT MAX(m.id) FROM hidden_chat_messages m join tickets t on t.id = m.ticket_id GROUP BY t.id)')
        )
        // ->when(!empty($pinned), fn($q) => $q->whereRaw("tickets.id IN ({$pinned})"))
        ->whereRaw("tickets.id IN ({$pinned})")
        ->whereNotIn('tickets.id', array_map(fn($d) => $d->tid, $data->all()))
        ->select(
          'tickets.*',
          'tickets.id AS tid',
          'reasons.name AS reason',
          'reasons.call_required AS important_reason',
          'messages.new_user_id AS last_message_user_id',
          'messages.created_at AS last_message_date',
          'hidden_chat_messages.created_at AS last_system_message_date',
          'bxc.name AS bx_name',
          'bxc.acronym AS bx_acronym',
          'bxc.domain AS bx_domain',
        )->get()->toArray();
    }

    $search = UserTrait::search();
    $users_collection = array();

    foreach ($search->data as $user) {
      $users_collection[$user->email] = $user;
    }
    unset($search);

    $all_data = array_merge($data->all(), $pinnedData);

    $all_ids = array_merge(...array_map(fn($t) => [$t->new_user_id, $t->new_manager_id], $all_data));
    $users_with_emails = DB::table('users')
      ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.id', array_values(array_unique($all_ids)))
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->get();
    unset($all_ids);

    foreach ($data as $ticket) {
      $u = $users_with_emails->where('id', $ticket->new_user_id)->first();
      $m = $users_with_emails->where('id', $ticket->new_manager_id)->first();
      $ticket->user = $users_collection[@$u->email]
        ?? UserTrait::tryToDefineUserEverywhere($u->crm_id, $u->email);
      $ticket->manager = $users_collection[@$m->email]
        ?? UserTrait::tryToDefineUserEverywhere($m->crm_id, $m->email);
    }
    foreach ($pinnedData as $ticket) {
      $u = $users_with_emails->where('id', $ticket->new_user_id)->first();
      $m = $users_with_emails->where('id', $ticket->new_manager_id)->first();
      $ticket->user = $users_collection[@$u->email]
        ?? UserTrait::tryToDefineUserEverywhere($u->crm_id, $u->email);
      $ticket->manager = $users_collection[@$m->email]
        ?? UserTrait::tryToDefineUserEverywhere($m->crm_id, $m->email);
    }
    unset($users_with_emails, $users_collection);

    // BX::getDataE();
    // $is_admin = BX::call('user.admin')['result'];
    // dd($is_admin, BX::call('user.current')['result']);
    // $message = 'Не созданы некоторые темы. Перейдите во вкладку "Темы" и заполните недостающие темы';

    $message = \App\Models\Manager::whereUserId($user_id)->exists() // || $is_admin
      ? 'Не созданы некоторые темы. Перейдите во вкладку "Темы" и заполните недостающие темы'
      : 'В настройке приложения допущены критические ошибки, обратитесь к администратору';
    $checksum = \App\Traits\ReasonTrait::Checksum();

    return response()->json([
      'status' => count($checksum) == 0,
      'checksum' => $checksum,
      'data' => TicketResource::collection($data)->response()->getData(),
      'pinned' => TicketResource::collection($pinnedData)->response()->getData(),
      'message' => $message
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(StoreTicketRequest $request)
  {
    // $new_user_id = \App\Models\User::firstWhere('email', Auth::user()->email)->id ?? 0;
    // $new_manager_id = \App\Models\User::firstWhere('email', Auth::user()->email)->id ?? 0;
    // dd(Auth::user(), $new_user_id, $new_user_id);
    if (in_array('Студент', explode(' ', Auth::user()->name))) {
      return response()->json([
        'status' => false,
        'data' => null,
        'message' => 'Студенческий аккаунт не имеет возможности создавать тикеты'
      ]);
    }

    $data = $request->validated();
    // $reason = Reason::first();
    $reason = \App\Traits\ReasonTrait::initByMessage($data['message']);

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
      // $id = TicketTrait::SelectResponsiveId($managers);
      $id = TicketTrait::selectResponsiveIdByMoronicDistributionType($managers);
      // dd($id, $managers);
      if ($id > 0) {
        $current_manager = array_values(array_filter($managers, fn($m) => $m['user_id'] == $id))[0];
      }



      // НИЧЕГО НЕ ЛОВИМ, ЕСЛИ АЙДИ = 0



      // $current_manager = $current_manager[array_key_first($current_manager)];
      // $current_manager = $id > 0
      //   ? array_filter($managers, fn($m) => $m['crm_id'] == $id)
      //   : $managers[array_key_first($managers)];
      // $current_manager = $managers->when($id > 0, fn($m) => $m->where('crm_id', $id))->first();
      // } else {
      //   $current_manager = $managers[array_key_first($managers)];
      //   // $current_manager = $managers->first();
    } else {
      $current_manager = $managers[0];
    }

    $user_crm_id = Auth::user()->crm_id;
    $manager_crm_id = $current_manager->crm_id;

    $data['user_id'] = $user_crm_id;
    $data['manager_id'] = $manager_crm_id;
    $data['new_user_id'] = Auth::user()->id;
    $data['new_manager_id'] = $current_manager->user_id;
    $bx_user_id = DB::table('bx_crms')
      ->join('bx_users AS bx', 'bx.bx_crm_id', 'bx_crms.id')
      ->where('bx.user_id', $data['new_user_id'])
      ->where('bx_crms.domain', env('CRM_DOMAIN'))
      ->pluck('bx_crms.id')
      ->first();
    $data['crm_id'] = @$bx_user_id ?? 1;
    $data['weight'] = $current_manager->weight;

    // $bx_crm_data = DB::table('bx_crms')
    //   ->join('bx_users AS bx', 'bx.bx_crm_id', 'bx_crms.id')
    //   ->where('bx.user_id', $data['new_user_id'])
    //   ->select('bx_crms.name', 'bx_crms.acronym', 'bx_crms.domain')
    //   ->first();
    // dd($bx_crm_data);
    $ticket = Ticket::create($data);
    Message::create([
      'content' => $data['message'],
      'user_crm_id' => $user_crm_id,
      'new_user_id' => $data['new_user_id'],
      'ticket_id' => $ticket->id,
    ]);
    if (isset($data['checked_instructions'])) {
      $instr = array_map(fn($i) => [
        'instruction_id' => $i,
        'ticket_id' => $ticket->id,
      ], $data['checked_instructions']);
      CheckedInstruction::insert($instr);
    }

    $ticket->reason = $reason->name;
    $ticket->user = UserTrait::tryToDefineUserEverywhere($user_crm_id, User::find($data['new_user_id'])->email);
    $new_manager_email = User::find($data['new_manager_id'])->email;
    $ticket->manager = UserTrait::tryToDefineUserEverywhere($manager_crm_id, $new_manager_email);

    $bx_crm_data = DB::table('bx_crms')
      ->join('bx_users AS bx', 'bx.bx_crm_id', 'bx_crms.id')
      ->where('bx.user_id', $data['new_user_id'])
      ->select('bx_crms.name', 'bx_crms.acronym', 'bx_crms.domain')
      ->first();
    $ticket->bx_name = $bx_crm_data->name;
    $ticket->bx_acronym = $bx_crm_data->acronym;
    $ticket->bx_domain = $bx_crm_data->domain;
    $ticket->important_reason = $reason->call_required;

    $resource = TicketResource::make($ticket);
    TicketTrait::SendMessageToWebsocket("{$new_manager_email}.ticket", [
      'ticket' => $resource,
    ]);
    $message = "Новый тикет №{$ticket->id}\nТема: {$ticket->reason}\nСоздатель: {$ticket->user->name}";
    TicketTrait::SendNotification($data['new_manager_id'], $message, $ticket->id, $reason->call_required);
    HiddenChatMessage::create([
      'content' => 'Тикет создан',
      'user_crm_id' => 0,
      'new_user_id' => 1,
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
      ->leftJoin('users AS u', 'u.id', 'tickets.new_user_id')
      ->leftJoin('bx_users AS bxu', 'bxu.user_id', 'u.id')
      ->leftJoin('bx_crms AS bxc', 'bxc.id', 'bxu.bx_crm_id')
      ->where('tickets.id', $id)
      ->select(
        'tickets.*',
        'tickets.id AS tid',
        'reasons.name AS reason',
        'reasons.call_required AS important_reason',
        'bxc.name AS bx_name',
        'bxc.acronym AS bx_acronym',
        'bxc.domain AS bx_domain',
      )
      ->find($id);

    if (!isset($ticket)) {
      return response()->json([
        'status' => false,
        'data' => null,
        'message' => 'Тикет не найден'
      ]);
    }

    $users_with_emails = DB::table('users')
      ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.id', [$ticket->new_user_id, $ticket->new_manager_id])
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->get();

    $u = $users_with_emails->where('id', $ticket->new_user_id)->first();
    $m = $users_with_emails->where('id', $ticket->new_manager_id)->first();
    unset($users_with_emails);

    $ticket->user = UserTrait::tryToDefineUserEverywhere($u->crm_id, $u->email);
    $ticket->manager = UserTrait::tryToDefineUserEverywhere($m->crm_id, $m->email);

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
      ->select(
        'tickets.*',
        'reasons.name AS reason',
        'reasons.call_required AS important_reason',
      )
      ->first();
    //   dd($ticket);
    // $bx_user_id = DB::table('bx_crms')
    //   ->join('bx_users AS bx', 'bx.bx_crm_id', 'bx_crms.id')
    //   ->where('bx.user_id', $ticket->new_user_id)
    //   ->where('bx_crms.domain', env('CRM_DOMAIN'))
    //   ->pluck('bx_crms.id')
    //   ->first();
    // $ticket->crm_id = @$bx_user_id ?? 1;

    if (!isset($ticket)) {
      return response()->json([
        'status' => false,
        'data' => $ticket,
        'message' => 'Тикет уже завершён'
      ]);
    }

    $users_with_emails = DB::table('users')
      ->leftJoin('bx_users', 'bx_users.user_id', 'users.id')
      ->whereIn('users.id', [$ticket->new_user_id, $ticket->new_manager_id])
      ->selectRaw('users.id, users.email, IFNULL(bx_users.crm_id, users.crm_id) AS crm_id')
      ->get();

    $u = $users_with_emails->where('id', $ticket->new_user_id)->first();
    $m = $users_with_emails->where('id', $ticket->new_manager_id)->first();
    unset($users_with_emails);

    $user = UserTrait::tryToDefineUserEverywhere($u->crm_id, $u->email);
    $manager = UserTrait::tryToDefineUserEverywhere($m->crm_id, $m->email);

    $bx_crm_data = DB::table('bx_crms')
      ->where('bx_crms.id', $ticket->crm_id)
      ->select('bx_crms.name', 'bx_crms.acronym', 'bx_crms.domain')
      ->first();

    if (isset($validated['active'])) {
      if ($validated['active'] == false) {
        $name = Auth::user()->name;
        HiddenChatMessage::create([
          'content' => "{$name} пометил тикет как решённый",
          'user_crm_id' => 0,
          'new_user_id' => 1,
          'ticket_id' => $ticket->id,
        ]);

        $message = "Тикет №{$ticket->id} был помечен менеджером как решённый\nПожалуйста, оцените работу менеджера";
        TicketTrait::SendMessageToWebsocket("{$u->email}.ticket.delete", [
          'id' => $ticket->id,
          'message' => null,
          'finished' => true,
        ]);
        TicketTrait::SendNotification($u->id, $message, $ticket->id);
      } else {
        $ticket_data = clone ($ticket);
        $ticket_data->active = 1;
        $ticket_data->user = $user;
        $ticket_data->manager = $manager;
        $ticket_data->bx_name = $bx_crm_data->name;
        $ticket_data->bx_acronym = $bx_crm_data->acronym;
        $ticket_data->bx_domain = $bx_crm_data->domain;
        $message = "Тикет №{$ticket_data->id} был возвращён в работу";

        TicketTrait::SendMessageToWebsocket("{$m->email}.ticket", [
          'ticket' => TicketResource::make($ticket_data),
        ]);
        TicketTrait::SendNotification($m->id, $message, $ticket_data->id);
        HiddenChatMessage::create([
          'content' => 'Тикет возвращён в работу',
          'user_crm_id' => 0,
          'new_user_id' => 1,
          'ticket_id' => $ticket_data->id,
        ]);
        unset($ticket_data);
      }
    }

    $needToNoteForResponsive = true;
    if (isset($validated['reason_id'])) {
      $reason = Reason::find($validated['reason_id']) ?? Reason::first();
      $validated['reason_id'] = $reason->id;
      $validated['weight'] = $reason->weight;

      $managers = TicketTrait::GetManagersForReason($reason->id);
      if (isset($managers)) {
        $current_manager = null;
        if (count($managers) > 1) {
          $responsive_id = TicketTrait::SelectResponsiveId($managers);
          if ($responsive_id > 0) {
            $current_manager = array_values(array_filter($managers, fn($m) => $m['user_id'] == $responsive_id))[0];
          }
        } else {
          $current_manager = $managers[0];
        }

        if ($current_manager != null) {
          $result = UserTrait::changeTheResponsive($id, $current_manager->user_id);
          $needToNoteForResponsive = $ticket->new_manager_id != $result['new_manager_id'];
          $validated['new_manager_id'] = $result['new_manager_id'];
          $manager = $result['new_manager'];
        }
      }
    }
    if (isset($validated['incompetence'])) {
      $ticket->incompetence = $validated['incompetence'];
    }
    if (isset($validated['technical_problem'])) {
      $ticket->technical_problem = $validated['technical_problem'];
    }

    $ticket->fill($validated);
    $ticket->save();

    $ticket = Ticket::join('reasons', 'reasons.id', 'tickets.reason_id')
      ->where('tickets.id', $id)
      ->select(
        'tickets.*',
        'reasons.name AS reason',
        'reasons.call_required AS important_reason',
      )
      ->first();
    $ticket->user = $user;
    $ticket->manager = $manager;

    $ticket->bx_name = $bx_crm_data->name;
    $ticket->bx_acronym = $bx_crm_data->acronym;
    $ticket->bx_domain = $bx_crm_data->domain;

    if (!isset($validated['active'])) {
      $another_recipients = \App\Models\Participant::join('users', 'users.id', 'participants.user_id')
        // ->join('bx_users', 'bx_users.user_id', 'users.id')
        ->whereTicketId($ticket->id)
        ->whereNot('participants.user_id', Auth::user()->id)
        ->pluck('users.email')->toArray();
      foreach ($another_recipients as $email) {
        TicketTrait::SendMessageToWebsocket("{$email}.ticket.patch", [
          'ticket' => $ticket,
        ]);
      }
      TicketTrait::SendMessageToWebsocket("{$u->email}.ticket.patch", [
        'ticket' => $ticket,
      ]);
      if ($needToNoteForResponsive) {
        TicketTrait::SendMessageToWebsocket("{$m->email}.ticket.patch", [
          'ticket' => $ticket,
        ]);
      }
    }

    if (isset($validated['reason_id'])) {
      $name = Auth::user()->name;
      HiddenChatMessage::create([
        'content' => "{$name} изменил тему на {$ticket->reason}",
        'user_crm_id' => 0,
        'new_user_id' => 1,
        'ticket_id' => $ticket->id,
      ]);
    }

    $message = "Тикет №{$ticket->id} " .
      (isset($validated['active']) && $validated['active'] == true
        ? "возобновлён"
        : "успешно изменён"
      );
    // if (isset($validated['active']) && $validated['active'] == true) {
    //   $message .= "возобновлён";
    // } else {
    //   $message .= "успешно изменён";
    // }
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
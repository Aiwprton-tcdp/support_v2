<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Traits\UserTrait;
use Illuminate\Http\Request;

class DetalizationController extends Controller
{
    // Детализация по активным тикетам
    public function activeTickets()
    {
        $managers = \App\Models\Manager::pluck('crm_id')->toArray();
    
        $tickets = Ticket::join('reasons', 'reasons.id', 'tickets.reason_id')
          ->selectRaw('reasons.name, manager_id, COUNT(tickets.id) tickets_count')
          ->groupBy('manager_id', 'reasons.id')->get();
    
        $search = UserTrait::search();
        $search_users = array_values(array_filter($search->data, fn($e) => in_array($e->crm_id, $managers)));
        unset($search);
    
        $users = [];
        foreach ($search_users as $user) {
          $users[$user->crm_id] = $user;
        }
        unset($search_users);
        $lost = [];
        $active = [];
    
        foreach ($tickets as $ticket) {
          if (in_array($ticket->manager_id, $managers)) {
            $ticket->manager = $users[$ticket->manager_id];
            unset($ticket->manager_id);
            array_push($active, $ticket);
          } else {
            $user = UserTrait::tryToDefineUserEverywhere($ticket->manager_id);
            $ticket->manager = $user;
            array_push($lost, $ticket);
          }
        }
    
        return response()->json([
          'status' => true,
          'data' => [
            'lost' => $lost,
            'active' => $active
          ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

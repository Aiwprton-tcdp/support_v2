<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInstructionRequest;
use App\Http\Requests\UpdateInstructionRequest;
use App\Http\Resources\InstructionResource;
use App\Models\Instruction;

class InstructionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reason_id = intval($this->prepare(request('reason_id')));
        $reason_name = $this->prepare(request('reason_name'));
        $ticket_id = intval($this->prepare(request('ticket_id')));

        $data = Instruction::join('reasons', 'reasons.id', 'instructions.reason_id')
            ->when(empty($ticket_id), fn($q) => $q->leftJoin('checked_instructions', 'checked_instructions.instruction_id', 'instructions.id'))
            ->when(
                !empty($ticket_id),
                fn($q) => $q->leftJoin(
                    'checked_instructions',
                    fn($q) => $q->on('checked_instructions.instruction_id', 'instructions.id')
                        ->where('checked_instructions.ticket_id', $ticket_id)
                )
            )
            ->when(!empty($reason_id), fn($q) => $q->where('instructions.reason_id', $reason_id))
            ->when(!empty($reason_name), fn($q) => $q->where('reasons.name', $reason_name))
            ->selectRaw('instructions.*, checked_instructions.id AS ciid')
            ->get();

        return response()->json([
            'status' => true,
            'data' => InstructionResource::collection($data)->response()->getData()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInstructionRequest $request)
    {
        $validated = $request->validated();
        $instruction = Instruction::firstOrNew([
            'name' => $validated['name'],
            'reason_id' => $validated['reason_id'],
        ]);

        if ($instruction->exists) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Инструкция с таким названием по данной теме уже существует'
            ]);
        }

        $instruction->content = $validated['content'];
        $instruction->save();

        return response()->json([
            'status' => true,
            'data' => InstructionResource::make($instruction),
            'message' => 'Инструкция успешно добавлена'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Instruction $instruction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInstructionRequest $request, $id)
    {
        $instruction = Instruction::findOrFail($id);
        $validated = $request->validated();

        if (isset($validated['name']) && $instruction->name == $validated['name']) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Инструкция с таким названием по данной теме уже существует'
            ]);
        }

        $instruction->fill($validated);
        $instruction->save();

        return response()->json([
            'status' => true,
            'data' => InstructionResource::make($instruction),
            'message' => 'Инструкция успешно изменена'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = Instruction::findOrFail($id);
        $result = $data->delete();
        $message = "Инструкция `{$data->name}` успешно удалена";

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => $message
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTemplateMessageRequest;
use App\Http\Requests\UpdateTemplateMessageRequest;
use App\Http\Resources\TemplateMessageResource;
use App\Models\TemplateMessage;

class TemplateMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $limit = intval(htmlspecialchars(trim(request('limit'))));

        $data = \Illuminate\Support\Facades\DB::table('template_messages')
            ->paginate($limit < 1 ? 100 : $limit);

        return response()->json([
            'status' => true,
            'data' => TemplateMessageResource::collection($data)->response()->getData()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTemplateMessageRequest $request)
    {
        $data = TemplateMessage::create($request->validated());
        return response()->json([
            'status' => true,
            'data' => TemplateMessageResource::make($data),
            'message' => 'Шаблон успешно добавлен'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(TemplateMessage $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTemplateMessageRequest $request, $id)
    {
        $data = TemplateMessage::findOrFail($id);
        $data->fill($request->validated());
        $data->save();

        return response()->json([
            'status' => true,
            'data' => TemplateMessageResource::make($data),
            'message' => 'Шаблон изменён'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $data = TemplateMessage::findOrFail($id);
        $result = $data->delete();

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => 'Шаблон успешно удалён'
        ]);
    }
}
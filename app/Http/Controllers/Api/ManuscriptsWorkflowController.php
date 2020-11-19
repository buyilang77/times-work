<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManuscriptRequest;
use App\Models\Manuscript;
use App\Models\User;
use App\Models\WorkflowManuscript;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManuscriptsWorkflowController extends Controller
{
    /**
     * @param Request $request
     * @param Manuscript $manuscript
     * @return JsonResponse
     */
    public function update(Request $request, Manuscript $manuscript): JsonResponse
    {
        $data = $request->validate(['status' => 'required|integer']);
        switch ($this->user()->type) {
            case User::TEXT_EDITOR:
                $data['text_editor_id'] = auth()->id();
                break;
            case User::WRITING_EDITOR:
                $data['writing_editor_id'] = auth()->id();
                break;
            case User::ADVANCED_EDITOR:
                $data['advanced_editor_id'] = auth()->id();
                break;
        }
        $manuscript->workflow()->update($data);
        return custom_response(null, 103);
    }

    /**
     * @param ManuscriptRequest $request
     * @param Manuscript $manuscript
     * @return JsonResponse
     */
    public function review(ManuscriptRequest $request, Manuscript $manuscript): JsonResponse
    {
        $data = $request->validated();
        $status = (int)$data['status'];
        $workflow = $manuscript->workflow;
        $workflow->status = $status;
        if ($status === WorkflowManuscript::STATUS_SUCCESS) {
            if ($workflow->getOriginal('status') === WorkflowManuscript::STATUS_REVIEW) {
                $media_db = $this->getMediaDatabase($data['media_id']);
                $item = [
                    'ChannelID'   => $data['channel_id'],
                    'InfoContent' => $data['content'],
                    'InfoTitle'   => $data['title'],
                    'InfoTime'    => now()->toDateTimeString(),
                    'InfoPicture' => $data['thumbnail'],
                    'IsCheck'     => 1,
                ];
                DB::connection($media_db)->table('info')->insert($item);
            }
        }
        $workflow->save();
        return custom_response(null, 103);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function channel(Request $request): JsonResponse
    {
        $data = $request->validate(['media_id' => 'required|integer']);

        $condition['IsShow'] = 1;
        $condition['IsEnable'] = 1;
        $condition['LanguageID'] = 1;
        $condition['ChannelModelID'] = 30;
        $media_db = $this->getMediaDatabase($data['media_id']);
        if (!$media_db) {
            return custom_response();
        }
        $item = DB::connection($media_db)->table('channel')->where($condition)->get(['ChannelID', 'ChannelName']);
        return custom_response($item);
    }

    /**
     * @param int $media_id
     * @return string
     */
    private function getMediaDatabase(int $media_id): string
    {
        $channel = null;
        switch ($media_id) {
            case Manuscript::TIMES:
                $channel = 'times';
                break;
            case Manuscript::HONOR:
                $channel = 'honor';
                break;
            case Manuscript::GOVERNMENT:
                $channel = 'government';
                break;
        }
        return $channel;
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $conversations = Conversation::with([
            'latestMessage.sender:id,first_name,last_name',
            'student:id,first_name,last_name',
            'company:id,first_name,last_name',
            'application.internshipOffer:id,title',
        ])
            ->withCount(['messages as unread_count' => function ($query) use ($user) {
                $query->whereNull('read_at')->where('sender_id', '!=', $user->id);
            }])
            ->where(function ($query) use ($user) {
                $query->where('student_id', $user->id)
                    ->orWhere('company_id', $user->id);
            })
            ->latest('updated_at')
            ->get()
            ->map(function (Conversation $conversation) use ($user) {
                $otherParticipant = $conversation->student_id === $user->id
                    ? $conversation->company
                    : $conversation->student;

                return [
                    'id' => $conversation->id,
                    'application_id' => $conversation->application_id,
                    'other_participant' => [
                        'id' => $otherParticipant->id,
                        'name' => trim($otherParticipant->first_name . ' ' . $otherParticipant->last_name),
                    ],
                    'offer_title' => $conversation->application->internshipOffer->title,
                    'last_message' => $conversation->latestMessage,
                    'unread_count' => $conversation->unread_count,
                    'created_at' => $conversation->created_at,
                    'updated_at' => $conversation->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
        ]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $user = $request->user();
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()
            ->with('sender:id,first_name,last_name')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation)
    {
        $this->authorize('sendMessage', $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = DB::transaction(function () use ($request, $conversation, $validated) {
            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $validated['body'],
            ]);

            $conversation->touch();

            return $message->load('sender:id,first_name,last_name');
        });

        return response()->json([
            'success' => true,
            'message' => $message,
        ], 201);
    }

    public function startForApplication(Request $request, Application $application)
    {
        $this->authorize('createConversation', $application);

        $conversation = DB::transaction(function () use ($application) {
            $lockedApplication = Application::whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            return Conversation::firstOrCreate(
                ['application_id' => $lockedApplication->id],
                [
                    'student_id' => $lockedApplication->student_id,
                    'company_id' => $lockedApplication->internshipOffer()->value('company_id'),
                ]
            );
        });

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
        ], $conversation->wasRecentlyCreated ? 201 : 200);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = Message::whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->whereHas('conversation', function ($query) use ($user) {
                $query->where('student_id', $user->id)
                    ->orWhere('company_id', $user->id);
            })
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Landlord;

use App\Enums\Landlord\NoticeChannel;
use App\Enums\Landlord\NoticeStatus;
use App\Models\Landlord\Notice;
use App\Services\Concerns\PaginatesRequests;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Manages landlord in-app and mail notice records.
 *
 * Domain: per-user notification ledger for the landlord panel.
 *
 * Invariants:
 * - New notices are created as unread; mail is not sent from this service.
 * - Only unread notices can be updated or marked read.
 * - Channel creation respects {@see notifications.email_enabled} and {@see notifications.in_app_enabled}.
 *
 * Side effects: creates, updates, soft-deletes, and restores {@see Notice} records;
 * reads {@see SettingService} for channel policy.
 */
class NoticeService
{
    use PaginatesRequests;

    public function __construct(private SettingService $settings) {}

    /**
     * Paginate notices using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, Notice>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Notice::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Load a notice with optional allowed relationships.
     */
    public function show(Notice $notice, Request $request): Notice
    {
        return $notice->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Record an unread notice. Does not send mail.
     *
     * @param  array{user_id: int, title: string, body: string, channel?: NoticeChannel|string}  $data
     *
     * @throws ValidationException When the requested notice channel is disabled.
     */
    public function store(array $data): Notice
    {
        $channel = $data['channel'] ?? NoticeChannel::InApp;
        $resolved = $channel instanceof NoticeChannel
            ? $channel
            : NoticeChannel::from((string) $channel);

        $this->ensureChannelEnabled($resolved);

        return Notice::query()->create([
            'user_id' => $data['user_id'],
            'title' => $data['title'],
            'body' => $data['body'],
            'channel' => $resolved,
            'status' => NoticeStatus::Unread,
            'read_at' => null,
        ])->load('user');
    }

    /**
     * Update title or body on an unread notice.
     *
     * @param  array{title?: string, body?: string}  $data
     *
     * @throws ValidationException When the notice is not unread.
     */
    public function update(Notice $notice, array $data): Notice
    {
        $this->ensureUnread($notice);

        $notice->update($data);

        return $notice->refresh();
    }

    /**
     * Mark an unread notice as read.
     *
     * @throws ValidationException When the notice is not unread.
     */
    public function read(Notice $notice): Notice
    {
        $this->ensureUnread($notice);

        $notice->update([
            'status' => NoticeStatus::Read,
            'read_at' => now(),
        ]);

        return $notice->refresh();
    }

    /**
     * Soft delete a notice.
     */
    public function destroy(Notice $notice): void
    {
        $notice->delete();
    }

    /**
     * Restore a soft-deleted notice.
     *
     * @throws HttpException When the notice is not trashed (404).
     */
    public function restore(Notice $notice): Notice
    {
        abort_unless($notice->trashed(), 404);

        $notice->restore();

        return $notice->refresh();
    }

    /**
     * Soft delete many notices.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Notice::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted notices.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        Notice::onlyTrashed()->whereKey($ids)->restore();
    }

    /**
     * Mark all unread notices for a user as read.
     */
    public function readAll(int $userId): int
    {
        return Notice::query()
            ->where('user_id', $userId)
            ->where('status', NoticeStatus::Unread)
            ->update([
                'status' => NoticeStatus::Read,
                'read_at' => now(),
            ]);
    }

    /**
     * Ensure the notice is unread before mutating.
     *
     * @throws ValidationException When the notice is not unread.
     */
    private function ensureUnread(Notice $notice): void
    {
        if ($notice->status === NoticeStatus::Unread) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'The notice is not unread.',
        ]);
    }

    /**
     * @throws ValidationException When the channel is disabled in settings.
     */
    private function ensureChannelEnabled(NoticeChannel $channel): void
    {
        $enabled = match ($channel) {
            NoticeChannel::Mail => (bool) $this->settings->value('notifications.email_enabled', true),
            NoticeChannel::InApp => (bool) $this->settings->value('notifications.in_app_enabled', true),
        };

        if ($enabled) {
            return;
        }

        throw ValidationException::withMessages([
            'channel' => ["The {$channel->value} notification channel is disabled."],
        ]);
    }
}

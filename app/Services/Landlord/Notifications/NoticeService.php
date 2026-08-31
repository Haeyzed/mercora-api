<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications;

use App\Enums\Landlord\NoticeChannel;
use App\Enums\Landlord\NoticeStatus;
use App\Models\Landlord\Notice;
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
 *
 * Side effects: creates, updates, soft-deletes, and restores {@see Notice} records.
 */
class NoticeService
{
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
     * Paginate notice select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return Notice::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Notice $notice): array => [
                'label' => $notice->title,
                'value' => $notice->id,
            ]);
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
     */
    public function store(array $data): Notice
    {
        return Notice::query()->create([
            'user_id' => $data['user_id'],
            'title' => $data['title'],
            'body' => $data['body'],
            'channel' => $data['channel'] ?? NoticeChannel::InApp,
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

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}

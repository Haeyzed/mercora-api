<?php

use App\Enums\Landlord\NoticeStatus;
use App\Models\Landlord\Notice;
use App\Models\Landlord\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('returns a paginated list of notices', function () {
        Notice::factory()->count(2)->create();

        $this->getJson('/api/landlord/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'user_id', 'title', 'body', 'channel', 'status'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    });

    it('paginates notices using the per_page query parameter', function () {
        Notice::factory()->count(3)->create();

        $this->getJson('/api/landlord/notifications?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    });

    it('filters notices by user id', function () {
        $user = User::factory()->create();
        Notice::factory()->for($user)->create();
        Notice::factory()->create();

        $this->getJson('/api/landlord/notifications?filter[user_id]='.$user->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user_id', $user->id);
    });

    it('filters notices by status', function () {
        Notice::factory()->read()->create();
        Notice::factory()->create();

        $this->getJson('/api/landlord/notifications?filter[status]=read')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'read');
    });

    it('searches notices by title', function () {
        Notice::factory()->create([
            'title' => 'Trial ending soon',
            'body' => 'The trial period ends next week.',
        ]);
        Notice::factory()->create([
            'title' => 'Welcome to Mercora',
            'body' => 'Thanks for joining the platform.',
        ]);

        $this->getJson('/api/landlord/notifications?search=Trial')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Trial ending soon');
    });

    it('searches notices by recipient name', function () {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        Notice::factory()->for($user)->create();
        Notice::factory()->create();

        $this->getJson('/api/landlord/notifications?search=Ada')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user_id', $user->id);
    });

    it('returns all notices when search is blank', function () {
        Notice::factory()->count(2)->create();

        $this->getJson('/api/landlord/notifications?search=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });

    it('ignores unknown filters instead of querying arbitrary columns', function () {
        Notice::factory()->create();

        $this->getJson('/api/landlord/notifications?filter[password]=secret')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });

    it('includes the recipient when requested', function () {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        Notice::factory()->for($user)->create();

        $this->getJson('/api/landlord/notifications?include=user')
            ->assertOk()
            ->assertJsonPath('data.0.user.name', 'Ada Lovelace');
    });
});

describe('store', function () {
    it('records an unread in-app notice', function () {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $this->postJson('/api/landlord/notifications', [
            'user_id' => $user->id,
            'title' => 'Invoice past due',
            'body' => 'Acme Stores has an open invoice that is past due.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.title', 'Invoice past due')
            ->assertJsonPath('data.body', 'Acme Stores has an open invoice that is past due.')
            ->assertJsonPath('data.channel', 'in_app')
            ->assertJsonPath('data.status', 'unread')
            ->assertJsonPath('data.read_at', null)
            ->assertJsonPath('data.user.name', 'Ada Lovelace');

        $this->assertDatabaseHas('notices', [
            'user_id' => $user->id,
            'title' => 'Invoice past due',
            'channel' => 'in_app',
            'status' => NoticeStatus::Unread->value,
        ]);
    });

    it('does not persist client-supplied status or read_at', function () {
        $user = User::factory()->create();

        $this->postJson('/api/landlord/notifications', [
            'user_id' => $user->id,
            'title' => 'Invoice past due',
            'body' => 'Acme Stores has an open invoice that is past due.',
            'status' => 'read',
            'read_at' => '2026-08-29T20:00:00Z',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'unread')
            ->assertJsonPath('data.read_at', null);
    });

    it('records a mail channel without sending mail', function () {
        Mail::fake();

        $user = User::factory()->create();

        $this->postJson('/api/landlord/notifications', [
            'user_id' => $user->id,
            'title' => 'Invoice past due',
            'body' => 'Acme Stores has an open invoice that is past due.',
            'channel' => 'mail',
        ])
            ->assertCreated()
            ->assertJsonPath('data.channel', 'mail')
            ->assertJsonPath('data.status', 'unread');

        Mail::assertNothingSent();
    });

    it('returns 422 when required notice fields are missing', function () {
        $this->postJson('/api/landlord/notifications', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'title', 'body']);
    });

    it('returns 422 when the user does not exist', function () {
        $this->postJson('/api/landlord/notifications', [
            'user_id' => 999,
            'title' => 'Invoice past due',
            'body' => 'Acme Stores has an open invoice that is past due.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    });
});

describe('show', function () {
    it('returns a single notice', function () {
        $notice = Notice::factory()->create();

        $this->getJson("/api/landlord/notifications/{$notice->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $notice->id)
            ->assertJsonMissingPath('data.user');
    });

    it('returns 404 when the notice does not exist', function () {
        $this->getJson('/api/landlord/notifications/999')
            ->assertNotFound();
    });
});

describe('update', function () {
    it('updates title and body on an unread notice', function () {
        $notice = Notice::factory()->create();

        $this->putJson("/api/landlord/notifications/{$notice->id}", [
            'title' => 'Updated title',
            'body' => 'Updated body',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.body', 'Updated body');
    });

    it('returns 422 when updating a read notice', function () {
        $notice = Notice::factory()->read()->create();

        $this->putJson("/api/landlord/notifications/{$notice->id}", [
            'title' => 'Too late',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('read', function () {
    it('marks an unread notice as read', function () {
        $this->travelTo('2026-08-29 20:00:00');

        $notice = Notice::factory()->create();

        $this->postJson("/api/landlord/notifications/{$notice->id}/read")
            ->assertOk()
            ->assertJsonPath('data.status', 'read')
            ->assertJsonPath('data.read_at', '2026-08-29T20:00:00.000000Z');

        $this->assertDatabaseHas('notices', [
            'id' => $notice->id,
            'status' => NoticeStatus::Read->value,
        ]);
    });

    it('returns 422 when the notice is already read', function () {
        $notice = Notice::factory()->read()->create();

        $this->postJson("/api/landlord/notifications/{$notice->id}/read")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('readAll', function () {
    it('marks all unread notices for the authenticated user as read', function () {
        $user = auth()->user();
        Notice::factory()->count(2)->for($user)->create();
        Notice::factory()->read()->for($user)->create();

        $this->postJson('/api/landlord/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('read', 2);

        expect(Notice::query()->where('user_id', $user->id)->where('status', NoticeStatus::Unread)->count())->toBe(0);
    });
});

describe('destroy', function () {
    it('soft deletes a notice', function () {
        $notice = Notice::factory()->create();

        $this->deleteJson("/api/landlord/notifications/{$notice->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($notice);
    });

    it('returns 404 when showing a soft-deleted notice', function () {
        $notice = Notice::factory()->create();
        $notice->delete();

        $this->getJson("/api/landlord/notifications/{$notice->id}")
            ->assertNotFound();
    });
});

describe('restore', function () {
    it('restores a soft-deleted notice', function () {
        $notice = Notice::factory()->create();
        $notice->delete();

        $this->postJson("/api/landlord/notifications/{$notice->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $notice->id);

        $this->assertNotSoftDeleted($notice);
    });

    it('returns 404 when the notice is not soft deleted', function () {
        $notice = Notice::factory()->create();

        $this->postJson("/api/landlord/notifications/{$notice->id}/restore")
            ->assertNotFound();
    });
});

describe('destroyMany', function () {
    it('soft deletes the given notices', function () {
        $first = Notice::factory()->create();
        $second = Notice::factory()->create();

        $this->deleteJson('/api/landlord/notifications/destroy-many', [
            'ids' => [$first->id, $second->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
    });

    it('returns 422 when ids are missing', function () {
        $this->deleteJson('/api/landlord/notifications/destroy-many', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted notices', function () {
        $first = Notice::factory()->create();
        $second = Notice::factory()->create();
        $first->delete();
        $second->delete();

        $this->postJson('/api/landlord/notifications/restore-many', [
            'ids' => [$first->id, $second->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
    });

    it('returns 422 when an id is not soft deleted', function () {
        $notice = Notice::factory()->create();

        $this->postJson('/api/landlord/notifications/restore-many', [
            'ids' => [$notice->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });
});

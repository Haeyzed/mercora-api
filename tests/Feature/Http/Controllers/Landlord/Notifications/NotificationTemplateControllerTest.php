<?php

use App\Enums\Landlord\NoticeChannel;
use App\Models\Landlord\NotificationTemplate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('returns a paginated list of notification templates', function () {
        NotificationTemplate::factory()->count(2)->create();

        $this->getJson('/api/landlord/notification-templates')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'key', 'name', 'channels', 'is_active'],
                ],
            ]);
    });

    it('filters templates by key', function () {
        NotificationTemplate::factory()->create(['key' => 'payment.successful']);
        NotificationTemplate::factory()->create(['key' => 'tenant.suspended']);

        $this->getJson('/api/landlord/notification-templates?filter[key]=payment.successful')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.key', 'payment.successful');
    });
});

describe('options', function () {
    it('returns label value pairs', function () {
        NotificationTemplate::factory()->create([
            'key' => 'payment.successful',
            'name' => 'Payment Successful',
        ]);

        $this->getJson('/api/landlord/notification-templates/options')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Payment Successful')
            ->assertJsonPath('data.0.value', 'payment.successful');
    });
});

describe('store', function () {
    it('creates a notification template', function () {
        $this->postJson('/api/landlord/notification-templates', [
            'key' => 'custom.alert',
            'name' => 'Custom Alert',
            'channels' => [NoticeChannel::InApp->value],
            'variables' => ['name'],
            'title' => 'Hello {{name}}',
            'body' => 'Body {{name}}',
        ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'custom.alert')
            ->assertJsonPath('data.title', 'Hello {{name}}');
    });

    it('rejects unknown placeholders', function () {
        $this->postJson('/api/landlord/notification-templates', [
            'key' => 'custom.alert',
            'name' => 'Custom Alert',
            'channels' => [NoticeChannel::InApp->value],
            'variables' => ['name'],
            'title' => 'Hello {{unknown}}',
            'body' => 'Body',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variables']);
    });
});

describe('show update destroy preview', function () {
    it('shows a template', function () {
        $template = NotificationTemplate::factory()->create([
            'key' => 'payment.successful',
            'name' => 'Payment Successful',
        ]);

        $this->getJson("/api/landlord/notification-templates/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.key', 'payment.successful');
    });

    it('updates a template', function () {
        $template = NotificationTemplate::factory()->create([
            'key' => 'payment.successful',
            'name' => 'Payment Successful',
        ]);

        $this->putJson("/api/landlord/notification-templates/{$template->id}", [
            'name' => 'Payment OK',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Payment OK');
    });

    it('deletes a template', function () {
        $template = NotificationTemplate::factory()->create([
            'key' => 'payment.successful',
        ]);

        $this->deleteJson("/api/landlord/notification-templates/{$template->id}")
            ->assertNoContent();

        expect(NotificationTemplate::query()->whereKey($template->id)->exists())->toBeFalse();
    });

    it('previews rendered content', function () {
        $template = NotificationTemplate::factory()->create([
            'variables' => ['name'],
            'title' => 'Hi {{name}}',
            'body' => 'Welcome {{name}}',
            'email_subject' => 'Sub {{name}}',
            'email_body' => 'Mail {{name}}',
        ]);

        $this->postJson("/api/landlord/notification-templates/{$template->id}/preview", [
            'data' => ['name' => 'Ada'],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Hi Ada')
            ->assertJsonPath('data.body', 'Welcome Ada')
            ->assertJsonPath('data.email_subject', 'Sub Ada');
    });
});

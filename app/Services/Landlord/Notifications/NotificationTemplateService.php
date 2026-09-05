<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications;

use App\Models\Landlord\NotificationTemplate;
use App\Services\Concerns\PaginatesRequests;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * CRUD, preview, and lookup for landlord notification templates.
 *
 * Domain: editable lifecycle message templates keyed by string (e.g. payment.successful).
 *
 * Invariants:
 * - Template content may only reference declared variables.
 * - Active lookup is used by the dispatcher before any delivery.
 *
 * Side effects: creates, updates, and deletes {@see NotificationTemplate} rows.
 */
class NotificationTemplateService
{
    use PaginatesRequests;

    public function __construct(private TemplateRenderer $renderer) {}

    /**
     * Paginate templates using model filter and search scopes.
     *
     * @return LengthAwarePaginator<int, NotificationTemplate>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return NotificationTemplate::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate template select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: string}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return NotificationTemplate::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (NotificationTemplate $template): array => [
                'label' => $template->name,
                'value' => $template->key,
            ]);
    }

    /**
     * Load a template.
     */
    public function show(NotificationTemplate $template): NotificationTemplate
    {
        return $template;
    }

    /**
     * Find an active template by key.
     */
    public function findActiveByKey(string $key): ?NotificationTemplate
    {
        return NotificationTemplate::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Create a notification template.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException When placeholders are invalid.
     */
    public function store(array $data): NotificationTemplate
    {
        $this->validateTemplateContent($data);

        return NotificationTemplate::query()->create($data);
    }

    /**
     * Update a notification template.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException When placeholders are invalid.
     */
    public function update(NotificationTemplate $template, array $data): NotificationTemplate
    {
        $merged = array_merge($template->toArray(), $data);
        $this->validateTemplateContent($merged);

        $template->update($data);

        return $template->refresh();
    }

    /**
     * Delete a notification template.
     */
    public function destroy(NotificationTemplate $template): void
    {
        $template->delete();
    }

    /**
     * Preview rendered template content without sending.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     *
     * @throws ValidationException When placeholders are invalid.
     */
    public function preview(NotificationTemplate $template, array $data = []): array
    {
        /** @var list<string> $variables */
        $variables = $template->variables ?? [];

        try {
            return [
                'title' => $this->renderer->render($template->title, $data, $variables),
                'body' => $this->renderer->render($template->body, $data, $variables),
                'email_subject' => $this->renderer->render($template->email_subject, $data, $variables),
                'email_body' => $this->renderer->render($template->email_body, $data, $variables),
                'push_title' => $this->renderer->render($template->push_title, $data, $variables),
                'push_body' => $this->renderer->render($template->push_body, $data, $variables),
                'sms_body' => $this->renderer->render($template->sms_body, $data, $variables),
            ];
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'data' => [$exception->getMessage()],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function validateTemplateContent(array $data): void
    {
        /** @var list<string> $variables */
        $variables = array_values(array_map('strval', $data['variables'] ?? []));

        try {
            $this->renderer->validateFields([
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'email_subject' => $data['email_subject'] ?? null,
                'email_body' => $data['email_body'] ?? null,
                'push_title' => $data['push_title'] ?? null,
                'push_body' => $data['push_body'] ?? null,
                'sms_body' => $data['sms_body'] ?? null,
            ], $variables);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'variables' => [$exception->getMessage()],
            ]);
        }
    }
}

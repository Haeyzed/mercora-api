<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World;

use App\Http\Requests\Shared\World\Concerns\AuthorizesWorldManagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate ids for a World destroyMany action.
 */
class DestroyManyRequest extends FormRequest
{
    use AuthorizesWorldManagement;
    use ResolvesWorldModel;

    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->worldModelClass()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $model = $this->resolveWorldModelClass();

        return [
            /**
             * Ids of records to soft delete.
             *
             * @example [1, 2, 3]
             */
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => $model === null
                ? ['required', 'integer']
                : ['required', 'integer', Rule::exists($model, 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ids' => 'ids',
            'ids.*' => 'id',
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return $this->validated('ids');
    }
}

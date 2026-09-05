<?php

use App\Services\Landlord\Notifications\TemplateRenderer;

it('replaces placeholders with scalar values', function () {
    $renderer = new TemplateRenderer;

    expect($renderer->render('Hi {{name}}', ['name' => 'Ada'], ['name']))->toBe('Hi Ada');
});

it('supports nested keys via data_get', function () {
    $renderer = new TemplateRenderer;

    expect($renderer->render('{{user.name}}', ['user' => ['name' => 'Ada']], ['user.name']))->toBe('Ada');
});

it('rejects unknown placeholders', function () {
    $renderer = new TemplateRenderer;

    $renderer->render('Hi {{unknown}}', [], ['name']);
})->throws(InvalidArgumentException::class);

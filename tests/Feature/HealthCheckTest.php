<?php

test('the application health endpoint is reachable', function () {
    $this->get('/up')
        ->assertOk();
});

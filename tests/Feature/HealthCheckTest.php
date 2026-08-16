<?php

test('the application health endpoint responds successfully', function () {
    $this->get('/up')->assertOk();
});

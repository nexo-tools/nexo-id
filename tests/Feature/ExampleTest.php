<?php

it('returns a successful response for the home page', function () {
    $this->get('/')->assertOk();
});

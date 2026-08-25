<?php

namespace Tests;

abstract class BrowserTestCase extends TestCase
{
    protected function usesViteAssets(): bool
    {
        return true;
    }
}

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit', 'Security');
uses(RefreshDatabase::class)->in('Feature', 'Security');

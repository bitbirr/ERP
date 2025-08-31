<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Application\Services\PosService;
use App\Application\Services\InventoryService;
use App\Services\GL\GlService;
use App\Domain\Audit\AuditLogger;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    protected $user;
    protected $mockGlService;
    protected $mockAuditLogger;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a default user for testing
        $this->user = User::factory()->create();

        // Set up authentication context
        Auth::shouldReceive('id')->andReturn($this->user->id);
        Auth::shouldReceive('user')->andReturn($this->user);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('guard')->andReturnSelf();

        // Create mocks for services
        $this->mockGlService = Mockery::mock(GlService::class);
        $this->mockAuditLogger = Mockery::mock(AuditLogger::class);

        // Set up default mock behaviors
        $this->mockAuditLogger->shouldReceive('log')->andReturn(null);
        $this->mockGlService->shouldReceive('post')->andReturn(null);
        $this->mockGlService->shouldReceive('validateDraft')->andReturn([]);
        $this->mockGlService->shouldReceive('createJournal')->andReturnUsing(function ($data) {
            return \App\Models\GlJournal::create(array_merge($data, [
                'status' => 'DRAFT',
                'journal_no' => $data['journal_no'] ?? 'TEST-' . uniqid(),
            ]));
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a PosService instance with mocked dependencies
     */
    protected function createPosService(?InventoryService $inventoryService = null, ?AuditLogger $auditLogger = null): PosService
    {
        $inventoryService = $inventoryService ?? new InventoryService();
        $auditLogger = $auditLogger ?? $this->mockAuditLogger;
        return new PosService($inventoryService, $auditLogger);
    }

    /**
     * Get the mocked GL service
     */
    protected function getMockGlService(): GlService
    {
        return $this->mockGlService;
    }

    /**
     * Get the mocked audit logger
     */
    protected function getMockAuditLogger(): AuditLogger
    {
        return $this->mockAuditLogger;
    }

    /**
     * Authenticate as a specific user
     */
    protected function actingAsUser(User $user = null): self
    {
        $user = $user ?? $this->user;
        Auth::shouldReceive('id')->andReturn($user->id);
        Auth::shouldReceive('user')->andReturn($user);
        return $this;
    }
}

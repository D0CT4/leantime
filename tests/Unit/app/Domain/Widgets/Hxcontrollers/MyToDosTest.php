<?php

namespace Tests\Unit\app\Domain\Widgets\Hxcontrollers;

use Leantime\Domain\Setting\Services\Setting;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Leantime\Domain\Widgets\Hxcontrollers\MyToDos;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TestCase as BaseTestCase;

/**
 * Test class for MyToDos HTMX controller
 */
class MyToDosTest extends BaseTestCase
{
    private MyToDos $controller;
    private $ticketsService;
    private $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->ticketsService = $this->createMock(TicketService::class);
        $this->settingsService = $this->createMock(Setting::class);
        
        // Initialize controller
        $this->controller = new MyToDos();
        $this->controller->init($this->ticketsService, $this->settingsService);
    }

    /**
     * Test group mapping for time-based groups
     */
    public function testMapTimeGroupToFields()
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('mapTimeGroupToFields');
        $method->setAccessible(true);

        // Test overdue mapping
        $result = $method->invoke($this->controller, 'overdue');
        $this->assertArrayHasKey('dateToFinish', $result);
        $this->assertEquals(date('Y-m-d', strtotime('yesterday')), $result['dateToFinish']);

        // Test thisWeek mapping
        $result = $method->invoke($this->controller, 'thisWeek');
        $this->assertArrayHasKey('dateToFinish', $result);
        $this->assertEquals(date('Y-m-d', strtotime('next friday')), $result['dateToFinish']);

        // Test later mapping (clears date)
        $result = $method->invoke($this->controller, 'later');
        $this->assertArrayHasKey('dateToFinish', $result);
        $this->assertEquals('', $result['dateToFinish']);

        // Test invalid mapping
        $result = $method->invoke($this->controller, 'invalid');
        $this->assertEmpty($result);
    }

    /**
     * Test group mapping for project-based groups
     */
    public function testMapProjectGroupToFields()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('mapProjectGroupToFields');
        $method->setAccessible(true);

        // Test valid project ID
        $result = $method->invoke($this->controller, '123');
        $this->assertArrayHasKey('projectId', $result);
        $this->assertEquals(123, $result['projectId']);

        // Test invalid project ID
        $result = $method->invoke($this->controller, 'invalid');
        $this->assertEmpty($result);

        // Test zero project ID
        $result = $method->invoke($this->controller, '0');
        $this->assertEmpty($result);
    }

    /**
     * Test group mapping for priority-based groups
     */
    public function testMapPriorityGroupToFields()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('mapPriorityGroupToFields');
        $method->setAccessible(true);

        // Test undefined priority (999)
        $result = $method->invoke($this->controller, '999');
        $this->assertArrayHasKey('priority', $result);
        $this->assertEquals('', $result['priority']);

        // Test valid priorities (1-4)
        for ($i = 1; $i <= 4; $i++) {
            $result = $method->invoke($this->controller, (string)$i);
            $this->assertArrayHasKey('priority', $result);
            $this->assertEquals($i, $result['priority']);
        }

        // Test invalid priority
        $result = $method->invoke($this->controller, '5');
        $this->assertEmpty($result);

        $result = $method->invoke($this->controller, 'invalid');
        $this->assertEmpty($result);
    }

    /**
     * Test main group mapping method
     */
    public function testMapGroupToFields()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('mapGroupToFields');
        $method->setAccessible(true);

        // Test time grouping
        $result = $method->invoke($this->controller, 'time', 'overdue');
        $this->assertArrayHasKey('dateToFinish', $result);

        // Test project grouping
        $result = $method->invoke($this->controller, 'project', '123');
        $this->assertArrayHasKey('projectId', $result);

        // Test priority grouping
        $result = $method->invoke($this->controller, 'priority', '3');
        $this->assertArrayHasKey('priority', $result);

        // Test invalid grouping
        $result = $method->invoke($this->controller, 'invalid', 'test');
        $this->assertEmpty($result);
    }

    /**
     * Test toggle task collapse functionality
     */
    public function testToggleTaskCollapse()
    {
        // Mock settings service to return 'open' initially
        $this->settingsService->expects($this->once())
            ->method('getSetting')
            ->with('user.123.taskCollapsed.456', 'open')
            ->willReturn('open');

        $this->settingsService->expects($this->once())
            ->method('saveSetting')
            ->with('user.123.taskCollapsed.456', 'closed');

        // Mock session
        session(['userdata.id' => 123]);

        $result = $this->controller->toggleTaskCollapse(['taskId' => 456]);
        $this->assertEquals('closed', $result);
    }

    /**
     * Test permission checking for user update task
     */
    public function testCanUserUpdateTask()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('canUserUpdateTask');
        $method->setAccessible(true);

        // Test with valid ticket that user can access
        $mockTicket = (object) ['id' => 123, 'headline' => 'Test Task'];
        $this->ticketsService->expects($this->once())
            ->method('getTicket')
            ->with(123)
            ->willReturn($mockTicket);

        $result = $method->invoke($this->controller, 123);
        $this->assertTrue($result);
    }

    /**
     * Test permission checking when user cannot access task
     */
    public function testCanUserUpdateTaskNoAccess()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('canUserUpdateTask');
        $method->setAccessible(true);

        // Test with ticket that user cannot access (service returns false)
        $this->ticketsService->expects($this->once())
            ->method('getTicket')
            ->with(999)
            ->willReturn(false);

        $result = $method->invoke($this->controller, 999);
        $this->assertFalse($result);
    }

    /**
     * Test input validation in date mapping
     */
    public function testDateMappingInputValidation()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('mapTimeGroupToFields');
        $method->setAccessible(true);

        // Test with various edge cases
        $testCases = [
            'overdue' => ['dateToFinish' => date('Y-m-d', strtotime('yesterday'))],
            'thisWeek' => ['dateToFinish' => date('Y-m-d', strtotime('next friday'))],
            'later' => ['dateToFinish' => ''],
            '' => [],
            null => [],
            'random' => [],
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($this->controller, $input);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }
}
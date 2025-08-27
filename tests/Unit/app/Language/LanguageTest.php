<?php

namespace Tests\Unit\app\Language;

use Tests\Unit\TestCase;

/**
 * Test class for language files
 */
class LanguageTest extends TestCase
{
    /**
     * Test that all required notification messages exist in the language file
     */
    public function testRequiredNotificationMessagesExist()
    {
        $languageFile = app_path('Language/en-US.ini');
        $this->assertFileExists($languageFile, 'Language file en-US.ini should exist');
        
        $content = file_get_contents($languageFile);
        
        // List of required notification messages for MyToDos widget
        $requiredMessages = [
            'notifications.group_changes_applied',
            'notifications.group_changes_partial', 
            'notifications.group_changes_failed',
            'notifications.sorting_error',
            'notifications.title_updated',
            'notifications.title_update_error',
            'notifications.date_updated',
            'notifications.date_update_error',
            'notifications.milestone_updated',
            'notifications.milestone_update_error',
            'notifications.status_updated',
            'notifications.status_update_error',
            'notifications.subtask_saved',
            'notifications.subtask_save_error',
        ];
        
        foreach ($requiredMessages as $message) {
            $this->assertStringContainsString($message, $content, "Language file should contain {$message}");
        }
    }
    
    /**
     * Test that the language file is properly formatted
     */
    public function testLanguageFileSyntax()
    {
        $languageFile = app_path('Language/en-US.ini');
        $parsedIni = parse_ini_file($languageFile);
        
        $this->assertIsArray($parsedIni, 'Language file should be valid INI format');
        $this->assertNotEmpty($parsedIni, 'Language file should not be empty');
    }
}
<?php

//file: Models/Template.php
declare(strict_types=1);

namespace App\Models;

use App\Enums\TemplateType;
use PDO;
use RuntimeException;

/**
 * Template Model
 *
 * Handles all template-related database operations for projects, tasks, milestones, and sprints
 */
class Template extends BaseModel
{
    protected string $table = 'templates';

    /**
     * Template properties
     */
    public ?int $id = null;
    public string $name;
    public string $description;
    public string $template_type = TemplateType::PROJECT->value;
    public ?int $company_id = null;
    public bool $is_default = false;
    public bool $is_deleted = false;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Define fillable fields
     */
    protected array $fillable = [
        'name',
        'description',
        'template_type',
        'company_id',
        'is_default',
    ];

    /**
     * Define searchable fields
     */
    protected array $searchable = [
        'name',
        'description',
    ];

    /**
     * Define validation rules
     */
    protected array $validationRules = [
        'name' => ['required', 'string', 'max:255'],
        'description' => ['required', 'string'],
        'template_type' => ['required', 'enum:App\Enums\TemplateType'],
        'is_default' => ['boolean'],
    ];

    /**
     * Valid template types
     * @deprecated Use TemplateType enum instead
     */
    public const TEMPLATE_TYPES = [
        'project' => 'Project',
        'task' => 'Task',
        'milestone' => 'Milestone',
        'sprint' => 'Sprint',
    ];

    /**
     * Get all available templates for a company and type
     *
     * @param string $templateType Template type (project, task, milestone, sprint)
     * @param int|null $companyId Company ID or null for global templates
     * @return array Array of template objects
     */
    public function getAvailableTemplates(string $templateType = 'project', ?int $companyId = null): array
    {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE is_deleted = 0
                    AND template_type = :template_type
                    AND (company_id IS NULL OR company_id = :company_id)
                    ORDER BY is_default DESC, name ASC";

            $stmt = $this->db->executeQuery($sql, [
                ':template_type' => $templateType,
                ':company_id' => $companyId,
            ]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get available templates: " . $e->getMessage());
        }
    }

    /**
     * Get all templates with optional filtering
     *
     * @param array $filters Optional filters (template_type, company_id, etc.)
     * @param int $limit Items per page
     * @param int $page Current page number
     * @return array Array of template objects
     */
    public function getAllTemplates(array $filters = [], int $limit = 10, int $page = 1): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $whereConditions = ['is_deleted = 0'];
            $params = [];

            if (!empty($filters['template_type'])) {
                $whereConditions[] = 'template_type = :template_type';
                $params[':template_type'] = $filters['template_type'];
            }

            if (isset($filters['company_id'])) {
                if ($filters['company_id'] === null) {
                    $whereConditions[] = 'company_id IS NULL';
                } else {
                    $whereConditions[] = 'company_id = :company_id';
                    $params[':company_id'] = $filters['company_id'];
                }
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "SELECT * FROM {$this->table} 
                    WHERE {$whereClause}
                    ORDER BY template_type ASC, is_default DESC, name ASC
                    LIMIT :limit OFFSET :offset";

            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $stmt = $this->db->executeQuery($sql, $params);

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get templates: " . $e->getMessage());
        }
    }

    /**
     * Get default template for a specific type
     *
     * @param string $templateType Template type (project, task, milestone, sprint)
     * @param int|null $companyId Company ID or null for global default
     * @return object|null Default template object or null if not found
     */
    public function getDefaultTemplate(string $templateType = 'project', ?int $companyId = null): ?object
    {
        try {
            // First try company-specific default
            if ($companyId) {
                $sql = "SELECT * FROM {$this->table} 
                        WHERE is_deleted = 0 
                        AND is_default = 1 
                        AND template_type = :template_type
                        AND company_id = :company_id 
                        LIMIT 1";

                $stmt = $this->db->executeQuery($sql, [
                    ':template_type' => $templateType,
                    ':company_id' => $companyId,
                ]);
                $template = $stmt->fetch(PDO::FETCH_OBJ);

                if ($template) {
                    return $template;
                }
            }

            // Fall back to global default
            $sql = "SELECT * FROM {$this->table} 
                    WHERE is_deleted = 0 
                    AND is_default = 1 
                    AND template_type = :template_type
                    AND company_id IS NULL 
                    LIMIT 1";

            $stmt = $this->db->executeQuery($sql, [':template_type' => $templateType]);

            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get default template: " . $e->getMessage());
        }
    }

    /**
     * Set a template as default for a company and type
     *
     * @param int $templateId Template ID to set as default
     * @param string $templateType Template type (project, task, milestone, sprint)
     * @param int|null $companyId Company ID or null for global default
     * @return bool Success status
     */
    public function setDefaultTemplate(int $templateId, string $templateType = 'project', ?int $companyId = null): bool
    {
        try {
            $this->db->beginTransaction();

            // Clear existing defaults for this company and type
            $sql = "UPDATE {$this->table} 
                    SET is_default = 0 
                    WHERE template_type = :template_type 
                    AND " . ($companyId ? "company_id = :company_id" : "company_id IS NULL");

            $params = [':template_type' => $templateType];
            if ($companyId) {
                $params[':company_id'] = $companyId;
            }
            $this->db->executeInsertUpdate($sql, $params);

            // Set new default
            $sql = "UPDATE {$this->table} 
                    SET is_default = 1 
                    WHERE id = :template_id";

            $this->db->executeInsertUpdate($sql, [':template_id' => $templateId]);

            $this->db->commit();

            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();

            throw new RuntimeException("Failed to set default template: " . $e->getMessage());
        }
    }

    /**
     * Get sprint templates with additional configuration data
     *
     * @param int|null $companyId Company ID or null for global templates
     * @param int|null $projectId Project ID for project-specific templates
     * @return array Array of sprint template objects with configuration
     */
    public function getSprintTemplates(?int $companyId = null, ?int $projectId = null): array
    {
        try {
            // Get basic sprint templates without the problematic join
            $sql = "SELECT t.*
                    FROM {$this->table} t
                    WHERE t.is_deleted = 0
                    AND t.template_type = 'sprint'
                    AND (t.company_id IS NULL OR t.company_id = :company_id)
                    ORDER BY t.is_default DESC, t.name ASC";

            $params = [':company_id' => $companyId];
            $stmt = $this->db->executeQuery($sql, $params);
            $templates = $stmt->fetchAll(PDO::FETCH_OBJ);

            // Add default configuration values from settings
            $settingsService = \App\Services\SettingsService::getInstance();

            foreach ($templates as $template) {
                // Add default sprint configuration from settings
                $template->sprint_length = $settingsService->getSettingInt('sprints', 'default_length', 2);
                $template->estimation_method = $settingsService->getSetting('sprints', 'estimation_method', 'story_points');
                $template->default_capacity = $settingsService->getSettingInt('sprints', 'default_capacity', 40);
                $template->include_weekends = $settingsService->getSettingBool('sprints', 'include_weekends', false);
                $template->auto_assign_subtasks = $settingsService->getSettingBool('sprints', 'auto_assign_subtasks', true);

                // Default ceremony settings
                $template->ceremony_settings = [
                    'sprint_planning' => ['enabled' => true, 'duration_hours' => 2],
                    'daily_standup' => ['enabled' => true, 'duration_minutes' => 15],
                    'sprint_review' => ['enabled' => true, 'duration_hours' => 1],
                    'sprint_retrospective' => ['enabled' => true, 'duration_hours' => 1],
                ];
            }

            return $templates;
        } catch (\Exception $e) {
            error_log("Failed to get sprint templates: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Create sprint template with configuration
     *
     * @param array $templateData Template basic data
     * @param array $configData Sprint-specific configuration
     * @return int Template ID
     */
    public function createSprintTemplate(array $templateData, array $configData = []): int
    {
        try {
            $this->db->beginTransaction();

            // Create the basic template
            $templateId = $this->create($templateData);

            // Create sprint-specific configuration if provided
            if (!empty($configData)) {
                $this->createSprintTemplateConfiguration($templateId, $configData);
            }

            $this->db->commit();

            return $templateId;
        } catch (\Exception $e) {
            $this->db->rollBack();

            throw new RuntimeException("Failed to create sprint template: " . $e->getMessage());
        }
    }

    /**
     * Create sprint template configuration using settings
     *
     * @param int $templateId Template ID
     * @param array $configData Configuration data
     * @return bool Success status
     */
    public function createSprintTemplateConfiguration(int $templateId, array $configData): bool
    {
        try {
            // Store sprint template configuration in settings instead of separate table
            $settingModel = new Setting();

            // Update global sprint settings with the provided configuration
            if (isset($configData['sprint_length'])) {
                $settingModel->updateSetting('sprints', 'default_length', (string)$configData['sprint_length']);
            }
            if (isset($configData['estimation_method'])) {
                $settingModel->updateSetting('sprints', 'estimation_method', $configData['estimation_method']);
            }
            if (isset($configData['default_capacity'])) {
                $settingModel->updateSetting('sprints', 'default_capacity', (string)$configData['default_capacity']);
            }
            if (isset($configData['include_weekends'])) {
                $settingModel->updateSetting('sprints', 'include_weekends', $configData['include_weekends'] ? '1' : '0');
            }
            if (isset($configData['auto_assign_subtasks'])) {
                $settingModel->updateSetting('sprints', 'auto_assign_subtasks', $configData['auto_assign_subtasks'] ? '1' : '0');
            }

            return true;
        } catch (\Exception $e) {
            error_log("Failed to create sprint template configuration: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Get sprint template configuration from settings
     *
     * @param int $templateId Template ID
     * @return object|null Configuration object or null if not found
     */
    public function getSprintTemplateConfiguration(int $templateId): ?object
    {
        try {
            // Get configuration from settings instead of separate table
            $settingsService = \App\Services\SettingsService::getInstance();

            $config = new \stdClass();
            $config->template_id = $templateId;
            $config->sprint_length = $settingsService->getSettingInt('sprints', 'default_length', 2);
            $config->estimation_method = $settingsService->getSetting('sprints', 'estimation_method', 'story_points');
            $config->default_capacity = $settingsService->getSettingInt('sprints', 'default_capacity', 40);
            $config->include_weekends = $settingsService->getSettingBool('sprints', 'include_weekends', false);
            $config->auto_assign_subtasks = $settingsService->getSettingBool('sprints', 'auto_assign_subtasks', true);
            $config->ceremony_settings = [
                'sprint_planning' => ['enabled' => true, 'duration_hours' => 2],
                'daily_standup' => ['enabled' => true, 'duration_minutes' => 15],
                'sprint_review' => ['enabled' => true, 'duration_hours' => 1],
                'sprint_retrospective' => ['enabled' => true, 'duration_hours' => 1],
            ];

            return $config;
        } catch (\Exception $e) {
            error_log("Failed to get sprint template configuration: " . $e->getMessage());

            return null;
        }
    }
}

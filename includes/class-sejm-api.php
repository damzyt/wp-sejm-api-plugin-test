<?php

class Sejm_API
{
    private static $initiated = false;

    public static function init()
    {
        if (!self::$initiated) {
            self::load_dependencies();
            self::init_hooks();
            self::$initiated = true;
        }
    }

    private static function load_dependencies()
    {
        require_once SEJM_API_PATH . 'includes/class-sejm-api-registrator.php';
        require_once SEJM_API_PATH . 'includes/class-sejm-api-importer.php';
        require_once SEJM_API_PATH . 'includes/class-sejm-api-admin.php';
        require_once SEJM_API_PATH . 'includes/class-sejm-api-blocks.php';
    }

    private static function init_hooks()
    {
        $registrator = new Sejm_API_Registrator();
        add_action('init', [$registrator, 'register_post_type']);

        $importer = new Sejm_API_Importer();
        $admin = new Sejm_API_Admin($importer);
        add_action('admin_menu', [$admin, 'add_menu_page']);
        add_filter('manage_posel_posts_columns', [$admin, 'add_custom_columns']);
        add_action('manage_posel_posts_custom_column', [$admin, 'render_custom_columns'], 10, 2);
        
        add_action('admin_enqueue_scripts', [$admin, 'enqueue_scripts']);
        add_action('wp_ajax_sejm_import_start', [$admin, 'ajax_start_import']);
        add_action('wp_ajax_sejm_import_process', [$admin, 'ajax_process_item']);

        add_filter('acf/settings/load_json', [self::class, 'acf_json_load_point']);
        
        new Sejm_API_Blocks();
    }

    public static function acf_json_load_point($paths)
    {
        $paths[] = SEJM_API_PATH . 'acf-json';
        return $paths;
    }

    public static function activate()
    {
        self::load_dependencies();
        $registrator = new Sejm_API_Registrator();
        $registrator->register_post_type();

        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        flush_rewrite_rules();
    }
}

<?php
/**
 * Plugin Name: itecsa Internet Technologies S.A.
 * Description: Restringe acciones críticas para usuarios que no estén autorizados.
 * TO-DO: Transformar este mu-plugin en una extensión para MainWP.
 */

// ============================================================================
// CONFIGURACIÓN PRINCIPAL - VARIABLES DE ENTORNO
// ============================================================================

// Usuarios autorizados explícitamente (username, email o ID)
$ITECSA_AUTHORIZED_USERS = ['itecsa_admin', 'itecsa_dev', 'soporte@itecsa.com', 'Enrique'];

// Email de alertas de seguridad
$ITECSA_SECURITY_ALERT_EMAIL = ['santiago.casals@itecsa.com.ar'];

// Configuración de contenido de email
$ITECSA_EMAIL_SUBJECT = '{SITE_NAME} - Alerta de Seguridad';
$ITECSA_EMAIL_BODY_TEMPLATE = <<<HTML
<p>Se ha detectado un intento de acceso no autorizado en el sitio web.</p>
<h3>Detalles del Sitio</h3>
<ul>
  <li><strong>Sitio:</strong> {SITE_NAME}</li>
  <li><strong>URL:</strong> {SITE_URL}</li>
  <li><strong>Fecha/Hora:</strong> {TIMESTAMP}</li>
</ul>
<h3>Detalles del Usuario</h3>
<ul>
  <li><strong>Username:</strong> {USERNAME}</li>
  <li><strong>Email:</strong> {USER_EMAIL}</li>
  <li><strong>Rol:</strong> {USER_ROLE}</li>
  <li><strong>IP:</strong> {IP_ADDRESS}</li>
  <li><strong>User Agent:</strong> {USER_AGENT}</li>
</ul>
<h3>Acción Bloqueada</h3>
<p>{ACTION_DETAILS}</p>
<p style="font-size:13px;background:#f8f8f8;padding:10px;border-radius:4px;font-family:monospace;">{MESSAGE}</p>
<hr style="border:none;border-top:1px solid #e5e5e5;"/>
<p style="font-size:12px;color:#666;">Este mensaje fue generado automáticamente por <strong>itecsa Internet Technologies S.A.</strong>. No responder a este email.</p>
HTML;

// Páginas del admin siempre permitidas (formato: archivo.php)
$ITECSA_ALLOWED_ADMIN_PAGES = [
    'profile.php', 'index.php', 'admin-ajax.php', 'admin-post.php', 
    'async-upload.php', 'media-upload.php', 'plugins.php', 
    'plugin-install.php', 'users.php', 'admin.php', 'edit.php',
    'post.php', 'post-new.php', 'media.php', 'upload.php',
    'nav-menus.php', 'widgets.php'
];

// Parámetros de página permitidos (para admin.php?page=XXXXX)
$ITECSA_ALLOWED_PAGE_PARAMS = [
    // Divi Theme Builder y opciones
    'et_', // Todos los parámetros que empiecen con et_ (Divi)
    
    // Otros constructores populares
    'elementor', 'elementor_', 
    'beaver-builder', 'fl-builder',
    'vc-', 'js_composer',
    'cornerstone', 'cs-',
    'fusion-', 'avada-',
    'ct_', // Oxygen Builder
    'brizy', 'brizy-',
    
    // Plugins comunes que necesitan acceso
    'wpseo_', 'yoast', // Yoast SEO
    'wpcf7', // Contact Form 7
    'wc-', 'woocommerce', // WooCommerce
    'wp-rocket', // WP Rocket
    'wordfence', // Wordfence
    'mailchimp', 'mc4wp', // MailChimp
    'wpforms', // WPForms
    'ninja-forms', // Ninja Forms
    'gravityforms', 'gf_', // Gravity Forms
    
    // WordPress core
    'custom-header', 'custom-background'
];

// Páginas críticas restringidas que pueden afectar la seguridad del sitio
$ITECSA_CRITICAL_RESTRICTED_PAGES = [
    'themes.php', 'theme-install.php', 'customize.php',
    'user-edit.php', 'update-core.php'
];

// Páginas de configuración específicas restringidas (admin.php con parámetros)
$ITECSA_RESTRICTED_ADMIN_PAGES = [
    'options-general.php', 'options-writing.php', 'options-reading.php',
    'options-discussion.php', 'options-media.php', 'options-permalink.php', 
    'options-privacy.php'
];

// Capacidades denegadas para usuarios no autorizados
$ITECSA_DENIED_CAPABILITIES = [
    'delete_plugins',
    'edit_plugins',
    'update_plugins',
    'switch_themes',
    'edit_theme_options',
    'update_core',
    'update_themes',
    'delete_themes',
    'edit_users',
    'delete_users',
    'create_users',
    'edit_files'
];

// Acciones AJAX permitidas en lista blanca
$ITECSA_ALLOWED_AJAX_ACTIONS = [
    'et_onboarding_get_account_status', // Divi ‑ Onboarding / Support Center
];

// Códigos de alerta de WP Activity Log para plugins
$ITECSA_PLUGIN_ALERT_CODES = [
    2051, // Plugin activated
    2052, // Plugin deactivated
    2053, // Plugin installed
    2054, // Plugin deleted
    2055, // Plugin updated
    2056, // Plugin modified
];

// Configuración de throttling de emails
$ITECSA_EMAIL_THROTTLE_MINUTES = 60; // Solo 1 email por usuario cada 60 minutos
$ITECSA_EMAIL_MAX_PER_HOUR = 5;      // Máximo 5 emails por hora en total
$ITECSA_EMAIL_ENABLED = true;        // Habilitar/deshabilitar emails completamente

// ============================================================================
// INICIO DEL PLUGIN
// ============================================================================

add_action('init', function () use (
    $ITECSA_AUTHORIZED_USERS,
    $ITECSA_SECURITY_ALERT_EMAIL,
    $ITECSA_ALLOWED_ADMIN_PAGES,
    $ITECSA_ALLOWED_PAGE_PARAMS,
    $ITECSA_CRITICAL_RESTRICTED_PAGES,
    $ITECSA_RESTRICTED_ADMIN_PAGES,
    $ITECSA_DENIED_CAPABILITIES,
    $ITECSA_ALLOWED_AJAX_ACTIONS,
    $ITECSA_PLUGIN_ALERT_CODES,
    $ITECSA_EMAIL_THROTTLE_MINUTES,
    $ITECSA_EMAIL_MAX_PER_HOUR,
    $ITECSA_EMAIL_ENABLED,
    $ITECSA_EMAIL_SUBJECT,
    $ITECSA_EMAIL_BODY_TEMPLATE
) {
    // Permitir parámetros adicionales configurados por el usuario
    $custom_allowed_params = get_option('itecsa_guard_custom_allowed_params', []);
    if (!empty($custom_allowed_params) && is_array($custom_allowed_params)) {
        $ITECSA_ALLOWED_PAGE_PARAMS = array_merge($ITECSA_ALLOWED_PAGE_PARAMS, $custom_allowed_params);
    }
    
    // Función para debug - log de páginas bloqueadas para revisión
    $log_blocked_page = function($page_info) {
        $debug_mode = get_option('itecsa_guard_debug_mode', false);
        if ($debug_mode) {
            error_log("ITECSA GUARD DEBUG: Página bloqueada para revisión: {$page_info}");
        }
    };

    $current_user = wp_get_current_user();

    // Solo usuarios explícitamente autorizados
    $is_authorized = in_array($current_user->user_login, $ITECSA_AUTHORIZED_USERS)
        || in_array($current_user->user_email, $ITECSA_AUTHORIZED_USERS)
        || in_array($current_user->ID, $ITECSA_AUTHORIZED_USERS);
    
    // Función para verificar si una página está permitida
    $is_page_allowed = function($pagenow, $allowed_pages, $allowed_params) {
        // Verificar si la página está en la lista de páginas permitidas
        if (in_array($pagenow, $allowed_pages)) {
            // Si es admin.php, verificar también el parámetro 'page'
            if ($pagenow === 'admin.php' && isset($_GET['page'])) {
                $page_param = $_GET['page'];
                
                // Verificar si el parámetro coincide exactamente o empieza con alguno permitido
                foreach ($allowed_params as $allowed_param) {
                    if ($page_param === $allowed_param || 
                        (substr($allowed_param, -1) === '_' && strpos($page_param, $allowed_param) === 0)) {
                        return true;
                    }
                }
                
                // Si no hay coincidencia en parámetros específicos, permitir ciertas páginas básicas
                $basic_allowed_params = [
                    'options-general.php', 'options-writing.php', 'options-reading.php',
                    'options-discussion.php', 'options-media.php', 'options-permalink.php'
                ];
                
                return !in_array($page_param, $basic_allowed_params);
            }
            return true;
        }
        
        return false;
    };

    if (!$is_authorized) {
        // Bloqueo de edición de archivos desde el editor de temas/plugins
        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }

        add_filter('user_has_cap', function ($allcaps, $caps, $args, $user) use ($current_user, $ITECSA_DENIED_CAPABILITIES) {
            // Permitir instalar plugins, pero NO desactivar ni borrar
            foreach ($ITECSA_DENIED_CAPABILITIES as $cap) {
                if (isset($allcaps[$cap])) {
                    $allcaps[$cap] = false;
                    // Definir función si aún no existe
                    if (!function_exists('itecsa_log_blocked_action')) {
                        function itecsa_log_blocked_action($user, $action) {
                            $message = "Itecsa Guard: Usuario bloqueado ({$user->user_login}) intentó: {$action}";
                            
                            // 1. Intentar WP Activity Log Premium primero
                            if (function_exists('wsal_freemius') || class_exists('WpSecurityAuditLog')) {
                                if (function_exists('wsal_freemius')) {
                                    // WP Activity Log 4.x+
                                    do_action('wsal_log', 9999, array(
                                        'Username' => $user->user_login,
                                        'UserRole' => implode(', ', $user->roles),
                                        'Action' => $action,
                                        'Plugin' => 'itecsa Internet Technologies S.A.',
                                        'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
                                    ));
                                } else {
                                    // WP Activity Log 3.x
                                    global $wsal;
                                    if ($wsal) {
                                        $wsal->alerts->Trigger(9999, array(
                                            'Username' => $user->user_login,
                                            'UserRole' => implode(', ', $user->roles),
                                            'Action' => $action,
                                            'Plugin' => 'itecsa Internet Technologies S.A.'
                                        ));
                                    }
                                }
                            }
                            // Enviar siempre correo de alerta
                            itecsa_send_security_alert($message, $user, $action);
                            
                            // 3. Siempre mantener el log en error_log como backup
                            error_log($message);
                        }
                    }
                    // Evitar logs duplicados dentro del mismo request
                    static $logged_caps = [];
                    if (!isset($logged_caps[$cap])) {
                        $logged_caps[$cap] = true;
                        itecsa_log_blocked_action($user, $cap);
                    }
                }
            }
            return $allcaps;
        }, 10, 4);

        // Ocultar solo el menú de Apariencia (no el de Plugins)
        add_action('admin_menu', function () {
            remove_menu_page('themes.php');
        }, 999);

        // Forzar que el menú Plugins aparezca si algo lo oculta
        add_action('admin_menu', function () {
            global $menu;
            foreach ($menu as $index => $item) {
                if (isset($item[2]) && $item[2] === 'plugins.php') {
                    $menu[$index][1] = 'read'; // Rebaja la capability necesaria
                }
            }
        }, 1000);

        // Interceptar intentos de activar/desactivar plugins MÁS TEMPRANO
        add_action('admin_action_activate', function () use ($current_user, $is_authorized) {
            if (!$is_authorized && isset($_GET['plugin'])) {
                // Prevenir logging de WP Activity Log
                add_filter('wsal_before_log_alert', '__return_false', 999);
                itecsa_log_plugin_action($current_user, 'activate', $_GET['plugin']);
                itecsa_error_message('activate_plugin');
            }
        }, 1);

        add_action('admin_action_deactivate', function () use ($current_user, $is_authorized) {
            if (!$is_authorized && isset($_GET['plugin'])) {
                // Prevenir logging de WP Activity Log
                add_filter('wsal_before_log_alert', '__return_false', 999);
                itecsa_log_plugin_action($current_user, 'deactivate', $_GET['plugin']);
                itecsa_error_message('deactivate_plugin');
            }
        }, 1);

        // Interceptar acciones masivas en admin_init
        add_action('admin_init', function () use ($current_user, $is_authorized) {
            if (!$is_authorized) {
                // Interceptar acciones masivas (bulk actions)
                if (isset($_POST['action']) && in_array($_POST['action'], ['activate-selected', 'deactivate-selected'])) {
                    // Prevenir logging de WP Activity Log
                    add_filter('wsal_before_log_alert', '__return_false', 999);
                    $plugins = isset($_POST['checked']) ? $_POST['checked'] : [];
                    itecsa_log_plugin_bulk_action($current_user, $_POST['action'], $plugins);
                    itecsa_error_message('bulk_' . $_POST['action']);
                }
                // También interceptar action2 (segundo dropdown de bulk actions)
                if (isset($_POST['action2']) && in_array($_POST['action2'], ['activate-selected', 'deactivate-selected'])) {
                    // Prevenir logging de WP Activity Log
                    add_filter('wsal_before_log_alert', '__return_false', 999);
                    $plugins = isset($_POST['checked']) ? $_POST['checked'] : [];
                    itecsa_log_plugin_bulk_action($current_user, $_POST['action2'], $plugins);
                    itecsa_error_message('bulk_' . $_POST['action2']);
                }
            }
        }, 1);

        // Redirección o mensaje personalizado en páginas críticas restringidas
        add_action('admin_init', function () use ($current_user, $is_authorized, $ITECSA_CRITICAL_RESTRICTED_PAGES, $ITECSA_RESTRICTED_ADMIN_PAGES) {
            global $pagenow;
            
            // Verificar páginas críticas
            if (!$is_authorized && in_array($pagenow, $ITECSA_CRITICAL_RESTRICTED_PAGES)) {
                itecsa_error_message('admin_page_' . $pagenow);
            }
            
            // Verificar páginas de configuración específicas (admin.php con parámetros restringidos)
            if (!$is_authorized && $pagenow === 'admin.php' && isset($_GET['page'])) {
                if (in_array($_GET['page'], $ITECSA_RESTRICTED_ADMIN_PAGES)) {
                    itecsa_error_message('admin_page_' . $_GET['page']);
                }
            }
        });

        // Ocultar notificaciones de actualización
        add_action('admin_head', function () {
            echo '<style>
                .update-nag, .update-plugins, .update-core, .notice-warning, .notice-error { display: none !important; }
            </style>';
        });

        // Bloquear acceso a la REST API para usuarios no autorizados
        add_filter('rest_authentication_errors', function ($result) use ($current_user, $is_authorized) {
            if (!$is_authorized) {
                itecsa_log_blocked_access($current_user, 'REST API Access', $_SERVER['REQUEST_URI'] ?? 'unknown');
                return new WP_Error('rest_forbidden', 'No tienes permisos para acceder a la API.', array('status' => 403));
            }
            return $result;
        });

        // Bloquear AJAX para acciones críticas
        add_action('admin_init', function () use ($current_user, $is_authorized, $ITECSA_ALLOWED_AJAX_ACTIONS) {
            // Detectar nombre de la acción AJAX solicitada (POST tiene prioridad sobre GET)
            $ajax_action = $_POST['action'] ?? $_GET['action'] ?? '';

            // Lista blanca (se puede ampliar con el filtro 'itecsa_guard_allowed_ajax_actions')
            $allowed_ajax_actions = apply_filters('itecsa_guard_allowed_ajax_actions', $ITECSA_ALLOWED_AJAX_ACTIONS);

            // Considerar seguras todas las acciones AJAX de Divi que empiecen por 'et_'
            $is_divi_action = strpos($ajax_action, 'et_') === 0;

            if (
                defined('DOING_AJAX') && DOING_AJAX && // Es una petición AJAX
                !$is_authorized &&                      // El usuario NO está autorizado
                !in_array($ajax_action, $allowed_ajax_actions, true) && // No está en lista blanca exacta
                !$is_divi_action                        // Ni es una acción de Divi que empiece por et_
            ) {
                itecsa_log_blocked_access($current_user, 'AJAX Critical Action', $ajax_action ?: 'unknown');
                itecsa_error_message('ajax_' . ($ajax_action ?: 'unknown'));
            }
        });

        // Bloquear acceso general al admin, salvo para ciertas páginas
        add_action('admin_init', function () use ($current_user, $is_authorized, $ITECSA_ALLOWED_ADMIN_PAGES, $ITECSA_ALLOWED_PAGE_PARAMS, $is_page_allowed, $log_blocked_page) {
            if (
                !$is_authorized &&
                !defined('DOING_AJAX') &&
                !defined('DOING_CRON') &&
                !$is_page_allowed($GLOBALS['pagenow'], $ITECSA_ALLOWED_ADMIN_PAGES, $ITECSA_ALLOWED_PAGE_PARAMS)
            ) {
                $page_info = $GLOBALS['pagenow'];
                if (isset($_GET['page'])) {
                    $page_info .= '?page=' . $_GET['page'];
                }
                
                // Log para debug si está habilitado
                $log_blocked_page($page_info);
                
                itecsa_log_blocked_access($current_user, 'Admin Page Access', $page_info);
                itecsa_error_message('admin_access_' . $page_info);
            }
        }, 1);

        // Interceptar logs de WP Activity Log para plugins cuando son bloqueados
        add_filter('wsal_before_log_alert', function ($should_log, $alert_code = null) use ($current_user, $is_authorized, $ITECSA_PLUGIN_ALERT_CODES) {
            if (!$is_authorized) {
                // Si es una acción de plugin y el usuario no está autorizado, no registrar en WP Activity Log
                if (in_array($alert_code, $ITECSA_PLUGIN_ALERT_CODES)) {
                    return false;
                }
                
                // También verificar si estamos en una acción de plugin por URL
                if (isset($_GET['action']) && in_array($_GET['action'], ['activate', 'deactivate']) && isset($_GET['plugin'])) {
                    return false;
                }
                
                // Verificar acciones masivas
                if (isset($_POST['action']) && in_array($_POST['action'], ['activate-selected', 'deactivate-selected'])) {
                    return false;
                }
                if (isset($_POST['action2']) && in_array($_POST['action2'], ['activate-selected', 'deactivate-selected'])) {
                    return false;
                }
            }
            
            return $should_log;
        }, 999, 2);
    }
});

// ============================================================================
// CONFIGURACIÓN ADICIONAL PARA itecsa Internet Technologies S.A.
// ============================================================================

/*
 * CONFIGURACIÓN ADICIONAL PARA ITECSA AGENCY GUARD
 * 
 * Para permitir páginas adicionales sin modificar el código, puedes usar estas opciones:
 * 
 * 1. Permitir parámetros de página adicionales:
 *    update_option('itecsa_guard_custom_allowed_params', ['mi_plugin_', 'otro_parametro']);
 * 
 * 2. Habilitar modo debug para ver qué páginas están siendo bloqueadas:
 *    update_option('itecsa_guard_debug_mode', true);
 *    // Revisa los logs de error para ver: "ITECSA GUARD DEBUG: Página bloqueada para revisión"
 * 
 * 3. Para desactivar el modo debug:
 *    update_option('itecsa_guard_debug_mode', false);
 * 
 * 4. CONTROL DE EMAILS DE ALERTA:
 *    // Deshabilitar emails completamente (RECOMENDADO para detener spam):
 *    update_option('itecsa_guard_disable_emails', true);
 *    
 *    // Volver a habilitar emails:
 *    update_option('itecsa_guard_disable_emails', false);
 *    
 *    // Cambiar límite de emails por hora (por defecto 5):
 *    update_option('itecsa_guard_max_emails_per_hour', 2);
 *    
 *    // Cambiar tiempo de throttling por usuario en minutos (por defecto 60):
 *    update_option('itecsa_guard_throttle_minutes', 120);
 * 
 * EJEMPLO DE USO:
 * Si tienes un plugin que usa admin.php?page=mi_plugin_configuracion
 * Ejecuta: update_option('itecsa_guard_custom_allowed_params', ['mi_plugin_']);
 * Esto permitirá todas las páginas que empiecen con 'mi_plugin_'
 * 
 * PÁGINAS AUTOMÁTICAMENTE PERMITIDAS:
 * - Todas las páginas de Divi (et_*)
 * - Elementor, Beaver Builder, Visual Composer
 * - WooCommerce, Yoast SEO, Contact Form 7
 * - Y muchos otros plugins populares
 * 
 * NOTA: Los cambios en las opciones requieren recarga de la página para tomar efecto.
 */

// ============================================================================
// FUNCIONES AUXILIARES PARA LOGGING Y UI
// ============================================================================

// Función para mostrar mensajes de error con botón "Volver atrás"
if (!function_exists('itecsa_error_message')) {
    function itecsa_error_message($blocked_function, $additional_info = '') {
        $message = 'Usted no tiene permisos para realizar esta acción.';
        $contact_info = 'Contacte con soporte@itecsa.com para más información.';
        $function_blocked = "Función bloqueada: {$blocked_function}";
        
        $html = '
        <div style="max-width: 600px; margin: 50px auto; padding: 30px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 30px;">
                <div style="background: #dc3232; color: white; width: 60px; height: 60px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 20px;">
                    ⚠️
                </div>
                <h1 style="color: #dc3232; margin: 0; font-size: 24px; font-weight: 600;">Acceso Denegado</h1>
            </div>
            
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 20px; margin-bottom: 25px;">
                <p style="margin: 0 0 10px 0; font-size: 16px; color: #991b1b; font-weight: 500;">' . $message . '</p>
                <p style="margin: 0 0 10px 0; font-size: 14px; color: #7f1d1d;">' . $contact_info . '</p>
                <p style="margin: 0; font-size: 12px; color: #991b1b; font-family: monospace; background: #fee2e2; padding: 8px; border-radius: 4px;">' . $function_blocked . '</p>
        ';
        
        if (!empty($additional_info)) {
            $html .= '<p style="margin: 10px 0 0 0; font-size: 12px; color: #7f1d1d;">' . $additional_info . '</p>';
        }
        
        $html .= '
            </div>
            
            <div style="text-align: center;">
                <button onclick="history.back()" style="
                    background: #0073aa; 
                    color: white; 
                    border: none; 
                    padding: 12px 24px; 
                    border-radius: 6px; 
                    font-size: 14px; 
                    font-weight: 500; 
                    cursor: pointer; 
                    text-decoration: none; 
                    display: inline-block;
                    transition: background 0.2s;
                    margin-right: 10px;
                " onmouseover="this.style.background=\'#005a87\'" onmouseout="this.style.background=\'#0073aa\'">
                    ← Volver Atrás
                </button>
                
                <a href="mailto:soporte@itecsa.com" style="
                    background: #f1f1f1; 
                    color: #333; 
                    border: 1px solid #ccc; 
                    padding: 12px 24px; 
                    border-radius: 6px; 
                    font-size: 14px; 
                    font-weight: 500; 
                    text-decoration: none; 
                    display: inline-block;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#e1e1e1'" onmouseout="this.style.background='#f1f1f1'">✉️ Contactar Soporte</a>
            </div>
            
                         <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e5e5; text-align: center; color: #666; font-size: 12px;">
                 <p style="margin: 0 0 10px 0;">Protegido por <strong>itecsa Internet Technologies S.A.</strong></p>
                 <details style="margin: 10px 0; text-align: left;">
                     <summary style="cursor: pointer; color: #0073aa; font-weight: 500;">💡 ¿Recibiendo muchos emails de alerta?</summary>
                     <div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px; font-size: 11px;">
                         <p style="margin: 0 0 8px 0;"><strong>Para detener los emails inmediatamente:</strong></p>
                         <p style="margin: 0 0 5px 0; font-family: monospace; background: #fff; padding: 5px; border-radius: 3px;">update_option(\'itecsa_guard_disable_emails\', true);</p>
                         <p style="margin: 0; color: #666;">Ejecutar este comando en WordPress para deshabilitar todas las alertas por email.</p>
                     </div>
                 </details>
             </div>
        </div>
        
        <style>
            body { background: #f1f1f1; margin: 0; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
        </style>';
        
        wp_die($html, 'Acceso Denegado - itecsa Internet Technologies S.A.', array('response' => 403));
    }
}

// Funciones auxiliares para logging
if (!function_exists('itecsa_log_plugin_action')) {
    function itecsa_log_plugin_action($user, $action, $plugin) {
        $log_message = "Itecsa Guard: Usuario bloqueado ({$user->user_login}) intentó {$action} plugin: {$plugin}";
        
        // 1. Intentar WP Activity Log Premium primero
        if (function_exists('wsal_freemius') || class_exists('WpSecurityAuditLog')) {
            $message = $action === 'activate' ? 'intentó activar' : 'intentó desactivar';
            if (function_exists('wsal_freemius')) {
                // WP Activity Log 4.x+
                do_action('wsal_log', 9998, array(
                    'Username' => $user->user_login,
                    'UserRole' => implode(', ', $user->roles),
                    'PluginName' => $plugin,
                    'Action' => $message,
                    'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                    'Plugin' => 'itecsa Internet Technologies S.A.'
                ));
            } else {
                // WP Activity Log 3.x
                global $wsal;
                if ($wsal) {
                    $wsal->alerts->Trigger(9998, array(
                        'Username' => $user->user_login,
                        'UserRole' => implode(', ', $user->roles),
                        'PluginName' => $plugin,
                        'Action' => $message,
                        'Plugin' => 'itecsa Internet Technologies S.A.'
                    ));
                }
            }
        }
        // Enviar siempre correo de alerta
        itecsa_send_security_alert($log_message, $user, "Plugin {$action}: {$plugin}");
        
        // 3. Siempre mantener el log en error_log como backup
        error_log($log_message);
    }
}

if (!function_exists('itecsa_log_plugin_bulk_action')) {
    function itecsa_log_plugin_bulk_action($user, $action, $plugins) {
        $plugin_list = is_array($plugins) ? implode(', ', $plugins) : $plugins;
        $log_message = "Itecsa Guard: Usuario bloqueado ({$user->user_login}) intentó {$action} plugins: {$plugin_list}";
        
        // 1. Intentar WP Activity Log Premium primero
        if (function_exists('wsal_freemius') || class_exists('WpSecurityAuditLog')) {
            $message = strpos($action, 'activate') !== false ? 'intentó activar múltiples' : 'intentó desactivar múltiples';
            if (function_exists('wsal_freemius')) {
                // WP Activity Log 4.x+
                do_action('wsal_log', 9997, array(
                    'Username' => $user->user_login,
                    'UserRole' => implode(', ', $user->roles),
                    'PluginCount' => count($plugins),
                    'PluginList' => $plugin_list,
                    'Action' => $message,
                    'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                    'Plugin' => 'itecsa Internet Technologies S.A.'
                ));
            } else {
                // WP Activity Log 3.x
                global $wsal;
                if ($wsal) {
                    $wsal->alerts->Trigger(9997, array(
                        'Username' => $user->user_login,
                        'UserRole' => implode(', ', $user->roles),
                        'PluginCount' => count($plugins),
                        'PluginList' => $plugin_list,
                        'Action' => $message,
                        'Plugin' => 'itecsa Internet Technologies S.A.'
                    ));
                }
            }
        }
        // Enviar siempre correo de alerta
        itecsa_send_security_alert($log_message, $user, "Bulk action {$action}: " . count($plugins) . " plugins");
        
        // 3. Siempre mantener el log en error_log como backup
        error_log($log_message);
    }
}

if (!function_exists('itecsa_log_blocked_access')) {
    function itecsa_log_blocked_access($user, $access_type, $details) {
        $log_message = "Itecsa Guard: Usuario bloqueado ({$user->user_login}) intentó acceso a: {$access_type} - {$details}";
        
        // 1. Intentar WP Activity Log Premium primero
        if (function_exists('wsal_freemius') || class_exists('WpSecurityAuditLog')) {
            if (function_exists('wsal_freemius')) {
                // WP Activity Log 4.x+
                do_action('wsal_log', 9996, array(
                    'Username' => $user->user_login,
                    'UserRole' => implode(', ', $user->roles),
                    'AccessType' => $access_type,
                    'Details' => $details,
                    'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                    'Plugin' => 'itecsa Internet Technologies S.A.'
                ));
            } else {
                // WP Activity Log 3.x
                global $wsal;
                if ($wsal) {
                    $wsal->alerts->Trigger(9996, array(
                        'Username' => $user->user_login,
                        'UserRole' => implode(', ', $user->roles),
                        'AccessType' => $access_type,
                        'Details' => $details,
                        'Plugin' => 'itecsa Internet Technologies S.A.'
                    ));
                }
            }
        }
        // Enviar siempre correo de alerta
        itecsa_send_security_alert($log_message, $user, "{$access_type}: {$details}");
        
        // 3. Siempre mantener el log en error_log como backup
        error_log($log_message);
    }
}

// Función para enviar alertas de seguridad por email con throttling agresivo
if (!function_exists('itecsa_send_security_alert')) {   
    function itecsa_send_security_alert($message, $user, $action_details) {
        // Acceder a las variables globales de configuración
        global $ITECSA_SECURITY_ALERT_EMAIL, $ITECSA_EMAIL_THROTTLE_MINUTES, $ITECSA_EMAIL_MAX_PER_HOUR, $ITECSA_EMAIL_ENABLED;
        
        // Verificar si los emails están deshabilitados (desde opciones de WordPress o configuración)
        $emails_disabled = get_option('itecsa_guard_disable_emails', false) || !$ITECSA_EMAIL_ENABLED;
        if ($emails_disabled) {
            error_log("ITECSA GUARD - EMAIL DESHABILITADO: {$message}");
            return;
        }
        
        // Evitar correos duplicados en el mismo request
        static $already_sent = [];
        $key = md5($message . '|' . $action_details);
        if (isset($already_sent[$key])) {
            return; // Ya se envió un correo con estos detalles
        }
        $already_sent[$key] = true;
        
        $current_time = time();
        
        // Obtener configuración de throttling (opciones de WordPress tienen prioridad)
        $throttle_minutes = get_option('itecsa_guard_throttle_minutes', $ITECSA_EMAIL_THROTTLE_MINUTES ?? 60);
        $throttle_seconds = $throttle_minutes * 60;
        $max_per_hour = get_option('itecsa_guard_max_emails_per_hour', $ITECSA_EMAIL_MAX_PER_HOUR ?? 5);
        
        // 1. THROTTLING POR USUARIO: Solo 1 email por usuario cada X minutos
        $user_key = 'itecsa_guard_email_throttle_user_' . $user->user_login;
        $last_email_time = get_transient($user_key);
        
        if ($last_email_time && ($current_time - $last_email_time) < $throttle_seconds) {
            error_log("ITECSA GUARD - EMAIL THROTTLED POR USUARIO: {$user->user_login} - Último email hace " . 
                     round(($current_time - $last_email_time) / 60) . " minutos (límite: {$throttle_minutes} min)");
            return;
        }
        
        // 2. THROTTLING GLOBAL: Máximo X emails por hora en total
        $global_key = 'itecsa_guard_email_count_hour';
        $email_count = get_transient($global_key) ?: 0;
        
        if ($email_count >= $max_per_hour) {
            error_log("ITECSA GUARD - EMAIL THROTTLED GLOBAL: Límite de {$max_per_hour} emails por hora alcanzado");
            return;
        }
        
        // 3. PROCEDER CON EL ENVÍO
        $to = $ITECSA_SECURITY_ALERT_EMAIL ?? ['santiago.casals@itecsa.com.ar'];
        $subject = $ITECSA_EMAIL_SUBJECT;
        
        // Obtener información del sitio
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $timestamp = current_time('mysql');
        
        // Construir el cuerpo del email con información de throttling
        $email_body = str_replace(
            ['{SITE_NAME}', '{SITE_URL}', '{TIMESTAMP}', '{USERNAME}', '{USER_EMAIL}', '{USER_ROLE}', '{IP_ADDRESS}', '{USER_AGENT}', '{ACTION_DETAILS}', '{MESSAGE}'],
            [$site_name, $site_url, $timestamp, $user->user_login, $user->user_email, implode(', ', $user->roles), $ip_address, $user_agent, $action_details, $message],
            $ITECSA_EMAIL_BODY_TEMPLATE
        );
        
        // Headers del email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <noreply@' . parse_url($site_url, PHP_URL_HOST) . '>',
            'Reply-To: noreply@' . parse_url($site_url, PHP_URL_HOST)
        );
        
        // Intentar enviar el email
        $email_sent = wp_mail($to, $subject, $email_body, $headers);
        
        $to_string = is_array($to) ? implode(', ', $to) : $to;
        
        if (!$email_sent) {
            // Si el email falla, registrar en error_log con más detalles
            error_log("ITECSA GUARD - EMAIL FALLIDO: No se pudo enviar alerta a {$to_string}. Detalles: {$message}");
            error_log("ITECSA GUARD - Sitio: {$site_name} ({$site_url}) - Usuario: {$user->user_login} - IP: {$ip_address}");
        } else {
            // 4. ACTUALIZAR CONTADORES DE THROTTLING SOLO SI EL EMAIL SE ENVIÓ EXITOSAMENTE
            
            // Actualizar el throttling por usuario
            set_transient($user_key, $current_time, $throttle_seconds);
            
            // Actualizar contador global (expira en 1 hora)
            set_transient($global_key, $email_count + 1, 3600);
            
            // Confirmar envío exitoso en error_log
            error_log("ITECSA GUARD - EMAIL ENVIADO: #" . ($email_count + 1) . "/{$max_per_hour} - {$to_string} - Acción: {$action_details}");
        }
    }
}

// ============================================================================
// REGISTRO DE ALERTAS PERSONALIZADAS EN WP ACTIVITY LOG
// ============================================================================

// Registrar alertas personalizadas en WP Activity Log
add_action('wsal_init', function() {
    if (function_exists('wsal_freemius') || class_exists('WpSecurityAuditLog')) {
        // Definir alertas personalizadas para itecsa Internet Technologies S.A.
        $custom_alerts = array(
            9996 => array(
                9996,
                WSAL_HIGH,
                'Usuario sin permisos intentó acceso restringido',
                'Usuario %Username% con rol %UserRole% intentó acceder a %AccessType%: %Details%. Bloqueado por itecsa Internet Technologies S.A.',
                'system',
                'blocked'
            ),
            9997 => array(
                9997,
                WSAL_CRITICAL,
                'Usuario sin permisos intentó acción masiva en plugins',
                'Usuario %Username% con rol %UserRole% %Action% plugins: %PluginList% (Total: %PluginCount%). Bloqueado por itecsa Internet Technologies S.A.',
                'plugins',
                'blocked'
            ),
            9998 => array(
                9998,
                WSAL_HIGH,
                'Usuario sin permisos intentó modificar plugin',
                'Usuario %Username% con rol %UserRole% %Action% el plugin %PluginName%. Bloqueado por itecsa Internet Technologies S.A.',
                'plugins',
                'blocked'
            ),
            9999 => array(
                9999,
                WSAL_MEDIUM,
                'Usuario sin permisos intentó acción restringida',
                'Usuario %Username% con rol %UserRole% intentó usar la capacidad %Action%. Bloqueado por itecsa Internet Technologies S.A.',
                'system',
                'blocked'
            )
        );

        // Registrar las alertas si el método existe
        if (method_exists('WSAL_AlertManager', 'SetEvents')) {
            WSAL_AlertManager::SetEvents($custom_alerts);
        } else {
            // Para versiones más antiguas
            global $wsal;
            if ($wsal && method_exists($wsal->alerts, 'RegisterGroup')) {
                $wsal->alerts->RegisterGroup(array(
                    __('itecsa Internet Technologies S.A.', 'wp-security-audit-log') => array(
                        __('Intentos bloqueados', 'wp-security-audit-log') => $custom_alerts
                    )
                ));
            }
        }
    }
});

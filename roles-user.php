<?php
/*
Plugin Name: Regras de Usuários
Description: Permite criar funções e gerenciar usuários.
Version: 1.0
Author: Teqii
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function adicionar_funcoes_usuario_personalizadas() {
    $role = get_role('adminis_amalfis');
    if (!$role) {   
        add_role(
            'adminis_amalfis',
            'Administrador amalfis',
            array(
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => true,
                'create_posts' => true,
                'edit_others_posts' => true,
                'publish_posts' => true,
                'manage_options' => true,
            )
        );
    }

    $admin_empresa = get_role('administrador_empresa');
    if (!$admin_empresa) {
        add_role(
            'administrador_empresa',
            'Administrador Loja',
            array(
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => true,
            )
        );
    }

    $gerente_empresa = get_role('gerente_empresa');
    if (!$gerente_empresa) {
        add_role(
            'gerente_empresa',
            'Gerente Loja',
            array(
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => false,
            )
        );
    }
}

add_action('init', 'adicionar_funcoes_usuario_personalizadas');
function personalizar_opcoes_usuario_admin_amalfis($all_roles) {
    $current_user = wp_get_current_user();

    if (in_array('adminis_amalfis', (array) $current_user->roles)) {
        $admin_roles = array(
            'administrador_empresa' => $all_roles['administrador_empresa'],
            'gerente_empresa' => $all_roles['gerente_empresa'],
            'customer' => $all_roles['customer'],
        );

        return $admin_roles;
    } elseif(in_array('administrador_empresa', (array) $current_user->roles)) {
        $admin_roles = array(
            'gerente_empresa' => $all_roles['gerente_empresa'],
            'customer' => $all_roles['customer'],
        );
        return $admin_roles;
    } elseif(in_array('gerente_empresa', (array) $current_user->roles)) {
        $admin_roles = array(
            'customer' => $all_roles['customer'],
        );
        return $admin_roles;
    }

    return $all_roles;
}

add_filter('editable_roles', 'personalizar_opcoes_usuario_admin_amalfis');
function adicionar_css_separado() {
    wp_enqueue_style('estilos-personalizados', plugin_dir_url(__FILE__) . '/css/admin-style.css');
}
add_action('admin_enqueue_scripts', 'adicionar_css_separado');

if (!class_exists('ACF_User_Management')) {
 class ACF_User_Management {
    // Constructor
    function __construct() {
        add_action('pre_get_users', array($this, 'filter_users_by_company'));
    }
    function filter_users_by_company($query) {
        if (is_admin() && isset($query->query['who']) && $query->query['who'] == '') {
            $current_user = wp_get_current_user();
            
            if (in_array('administrador_empresa', $current_user->roles) || in_array('gerente_empresa', $current_user->roles)) {
                $user_units = get_user_meta($current_user->ID, 'name', true);
    
                if ($user_units) {
                    // Obtém todos os usuários que compartilham a mesma unidade do usuário atual
                    $args = array(
                        'meta_key' => 'name',
                        'meta_value' => $user_units,
                        'meta_compare' => '=',
                        'fields' => 'ID'
                    );
    
                    $users_query = new WP_User_Query($args);
                    $users = $users_query->get_results();
    
                    if ($users) {
                        $user_ids = wp_list_pluck($users, 'ID');
                        $query->set('include', $user_ids);
                    }
                }
            }
        }
    }

 }   
    $acf_user_management = new ACF_User_Management();
    // Add admin menu
    function acf_user_management_init_admin_menu() {
     add_menu_page(
   'Gerenciar Usuários',
   'Gerenciar Usuários',
   'manage_options',
   'manage_users',
   'acf_user_management_manage_users_callback',
   'dashicons-admin-users',
   6
     );
    }
    add_action('admin_menu', 'acf_user_management_init_admin_menu');
    
    function acf_user_management_manage_users_callback() {
        $current_user = wp_get_current_user();
        $user_units = get_user_meta($current_user->ID, 'unidades_usuario', true);
    
        if ($user_units) {
            $role_filter = isset($_GET['role']) ? $_GET['role'] : '';
    
            // Array de papéis que você deseja filtrar
            $allowed_roles = array('gerente_empresa', 'cliente');
    
            // Verifique se o papel filtrado está entre os papéis permitidos
            if (!empty($role_filter) && in_array($role_filter, $allowed_roles)) {
                $users = get_users(array(
                    'meta_query' => array(
                        array(
                            'key' => 'unidades_usuario',
                            'value' => serialize($user_units),
                            'compare' => 'LIKE',
                        ),
                    ),
                    'role__in' => array($role_filter),
                    'fields' => 'all',
                ));
            } else {
                $users = get_users(array(
                    'meta_query' => array(
                        array(
                            'key' => 'unidades_usuario',
                            'value' => serialize($user_units),
                            'compare' => 'LIKE',
                        ),
                    ),
                    'fields' => 'all',
                ));
            }
    
            echo '<h2>Todos os Usuários</h2>';
            echo '<div class="wrap">';
            echo '<a href="users.php?role=gerente_empresa">Gerente Empresa</a> | ';
            echo '<a href="users.php?role=customer">Cliente</a>';
    
            if ($role_filter === 'gerente_empresa') {
                echo '<h3>Gerente Empresa</h3>';
                $users = get_users(array(
                    'meta_query' => array(
                        array(
                            'key' => 'unidades_usuario',
                            'value' => serialize($user_units),
                            'compare' => 'LIKE',
                        ),
                    ),
                    'fields' => 'all',
                ));
        
                echo '<h2>Todos os usuários</h2>';
                echo '<div class="wrap">';
                echo '<table class="widefat fixed" cellspacing="0">';
                echo '<thead>';
                echo '<tr>';
                echo '<th class="manage-column column-username">Nome do usuário</th>';
                echo '<th class="manage-column column-name">Nome</th>';
                echo '<th class="manage-column column-email">E-mail</th>';
                echo '<th class="manage-column column-role">Função</th>';
                echo '<th class="manage-column column-edit">Editar</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                foreach ($users as $user) {
                    echo '<tr>';
                    echo '<td class="username column-username">' . esc_html($user->user_login) . '</td>';
                    echo '<td class="name column-name">' . esc_html($user->display_name) . '</td>';
                    echo '<td class="email column-email">' . esc_html($user->user_email) . '</td>';
                    echo '<td class="role column-role">' . esc_html(implode(', ', $user->roles)) . '</td>';
                    echo '<td class="edit column-edit"><a href="' . esc_attr(get_edit_user_link($user->ID)) . '">Editar</a></td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
            } elseif ($role_filter === 'cliente') {
                echo '<h3>Cliente</h3>';
                // Exibir a tabela para usuários com papel de Cliente
                echo '<table class="widefat fixed" cellspacing="0">';
                // Código para exibir os usuários Cliente...
            } else {
                // Exibir a tabela para todos os usuários
                $users = get_users(array(
                    'meta_query' => array(
                        array(
                            'key' => 'unidades_usuario',
                            'value' => serialize($user_units),
                            'compare' => 'LIKE',
                        ),
                    ),
                    'fields' => 'all',
                ));
                echo '<div class="wrap">';
                echo '<table class="widefat fixed" cellspacing="0">';
                echo '<thead>';
                echo '<tr>';
                echo '<th class="manage-column column-username">Nome do usuário</th>';
                echo '<th class="manage-column column-name">Nome</th>';
                echo '<th class="manage-column column-email">E-mail</th>';
                echo '<th class="manage-column column-role">Função</th>';
                echo '<th class="manage-column column-edit">Editar</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                foreach ($users as $user) {
                    echo '<tr>';
                    echo '<td class="username column-username">' . esc_html($user->user_login) . '</td>';
                    echo '<td class="name column-name">' . esc_html($user->display_name) . '</td>';
                    echo '<td class="email column-email">' . esc_html($user->user_email) . '</td>';
                    echo '<td class="role column-role">' . esc_html(implode(', ', $user->roles)) . '</td>';
                    echo '<td class="edit column-edit"><a href="' . esc_attr(get_edit_user_link($user->ID)) . '">Editar</a></td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
            }
        }
    }
    
}
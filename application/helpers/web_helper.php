<?php

function is_logged_in()
{
    $CI = get_instance();
    if (!$CI->session->userdata("email")) {
        redirect("auth");
    } else {
        $role_id    = $CI->session->userdata("role_id");
        $menu       = $CI->uri->segment(1);

        $queryMenu  = $CI->db->get_where("user_menu", ["menu" => $menu])->row_array();
        $menu_id    = $queryMenu["id"];

        $userAccess = $CI->db->get_where("user_access_menu", [
            "role_id"   => $role_id,
            "menu_id"   => $menu_id
        ]);

        if ($userAccess->num_rows() < 1) {
            redirect("auth/blocked");
        }
    }
}

function check_access($role_id, $menu_id)
{
    $CI = get_instance();
    $result     = $CI->db->get_where("user_access_menu", [
        "role_id"   => $role_id,
        "menu_id"   => $menu_id
    ]);

    if ($result->num_rows() > 0) {
        return "checked='checked'";
    }
}

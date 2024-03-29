<?php

class Auth extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }


    public function index()
    {
        if ($this->session->userdata("email")) {
            redirect("user");
        }

        $this->form_validation->set_rules("email", "Email", "trim|required|valid_email", [
            "required"      => "Please insert your email"
        ]);
        $this->form_validation->set_rules("password", "Password", "trim|required", [
            "required"      => "Please insert your Password"
        ]);

        if ($this->form_validation->run() == false) {
            $data   = array(
                "page_title"    => "Login Page"
            );
            $this->load->view("templates/auth_header", $data);
            $this->load->view("auth/login", $data);
            $this->load->view("templates/auth_footer");
        } else {
            // validasi success
            $this->_login();
        }
    }


    private function _login()
    {
        $email      = $this->input->post("email");
        $password   = $this->input->post("password");

        $user       = $this->db->get_where("user", array(
            "email"     => $email
        ))->row_array();

        if ($user) {
            if ($user["is_active"] == 1) {
                if (password_verify($password, $user["password"])) {
                    $data   = array(
                        "email"     => $user["email"],
                        "role_id"   => $user["role_id"]
                    );
                    $this->session->set_userdata($data);
                    if ($user["role_id"] == 1) {
                        redirect("admin");
                    } else {
                        redirect("user");
                    }
                } else {
                    $this->session->set_flashdata("message", "<div class='alert alert-danger'
                    role='alert'>Wrong password!</div>");
                    redirect("auth");
                }
            } else {
                $this->session->set_flashdata("message", "<div class='alert alert-danger'
                role='alert'>This email has not been activated!</div>");
                redirect("auth");
            }
        } else {
            $this->session->set_flashdata("message", "<div class='alert alert-danger'
            role='alert'>This email is not registered!</div>");
            redirect("auth");
        }
    }


    public function register()
    {
        if ($this->session->userdata("email")) {
            redirect("user");
        }

        $this->form_validation->set_rules("name", "Name", "trim|required", [
            "required"      => "Please insert your name!"
        ]);
        $this->form_validation->set_rules("email", "Email", "trim|required|valid_email|is_unique[user.email]", [
            "required"      => "Please insert your email!",
            "is_unique"     => "This email has been registered!"
        ]);
        $this->form_validation->set_rules("password1", "Password", "trim|required|min_length[3]|matches[password2]", [
            "required"      => "Please insert password!",
            "min_length"    => "Password too short!",
            "matches"       => "Password dont match!",
        ]);
        $this->form_validation->set_rules("password2", "Password", "trim|required|matches[password1]");

        if ($this->form_validation->run() == false) {
            $data   = array(
                "page_title"    => "Register Page"
            );
            $this->load->view("templates/auth_header", $data);
            $this->load->view("auth/register", $data);
            $this->load->view("templates/auth_footer");
        } else {
            $email  = $this->input->post("email", true);
            $data   = array(
                "name"          => htmlspecialchars($this->input->post("name", true)),
                "email"         => htmlspecialchars($email),
                "image"         => "default.jpg",
                "password"      => password_hash($this->input->post("password1"), PASSWORD_DEFAULT),
                "role_id"       => 2,
                "is_active"     => 0,
                "date_created"  => time(),
            );

            // siapkan token
            $token      = base64_encode(random_bytes(32));
            $user_token = array(
                "email"         => $email,
                "token"         => $token,
                "date_created"  => time()
            );

            $this->db->insert("user", $data);
            $this->db->insert("user_token", $user_token);

            $this->_sendEmail($token, "verify");

            $this->session->set_flashdata("message", "<div class='alert alert-success'
            role='alert'>Congratulations! your account has been created. Please activate your account!</div>");
            redirect("auth");
        }
    }


    private function _sendEmail($token, $type)
    {
        $config     = array(
            "protocol"      => "smtp",
            "smtp_host"     => "ssl://smtp.googlemail.com",
            "smtp_user"     => "nmochammad99@gmail.com",
            "smtp_pass"     => "Gdmnasir99",
            "smtp_port"     => 465,
            "mailtype"      => "html",
            "charset"       => "utf-8",
            "newline"       => "\r\n",
        );

        $this->email->initialize($config);

        $this->email->from("nmochammad99@gmail.com", "Web site");
        $this->email->to($this->input->post("email"));

        if ($type == "verify") {
            $this->email->subject("Account Verification");
            $this->email->message("Click this link to verify your account : <a href='" . base_url() . "auth/verify?email=" . $this->input->post("email") . "&token=" . urlencode($token) . "'>Active</a>");
        } else {
            $this->email->subject("Reset Password");
            $this->email->message("Click this link to reset your password : <a href='" . base_url() . "auth/resetpassword?email=" . $this->input->post("email") . "&token=" . urlencode($token) . "'>Reset Password</a>");
        }

        if ($this->email->send()) {
            return true;
        } else {
            echo $this->email->print_debugger();
            die;
        }
    }


    public function verify()
    {
        $email  = $this->input->get("email");
        $token  = $this->input->get("token");

        $user   = $this->db->get_where("user", [
            "email" => $email
        ])->row_array();

        if ($user) {
            $user_token = $this->db->get_where("user_token", [
                "token" => $token
            ])->row_array();

            if ($user_token) {
                if (time() - $user_token["date_created"] < (60 * 60 * 24)) {
                    $this->db->set("is_active", 1);
                    $this->db->where("email", $email);
                    $this->db->update("user");

                    $this->db->delete("user_token", [
                        "email"     => $email
                    ]);

                    $this->session->set_flashdata("message", "<div class='alert alert-success'
                    role='alert'>" . $email . " has been activated! Please login.</div>");
                    redirect("auth");
                } else {
                    $this->db->delete("user", ["email" => $email]);
                    $this->db->delete("user_token", ["email" => $email]);

                    $this->session->set_flashdata("message", "<div class='alert alert-danger'
                    role='alert'>Account activation failed! Token expired.</div>");
                    redirect("auth");
                }
            } else {
                $this->session->set_flashdata("message", "<div class='alert alert-danger'
                role='alert'>Account activation failed! Wrong token.</div>");
                redirect("auth");
            }
        } else {
            $this->session->set_flashdata("message", "<div class='alert alert-danger'
            role='alert'>Account activation failed! Wrong email.</div>");
            redirect("auth");
        }
    }


    public function resetPassword()
    {
        $email  = $this->input->get("email");
        $token  = $this->input->get("token");

        $user   = $this->db->get_where("user", [
            "email" => $email
        ])->row_array();

        if ($user) {
            $user_token = $this->db->get_where("user_token", [
                "token" => $token
            ])->row_array();

            if ($user_token) {
                $this->session->set_userdata("reset_email", $email);
                $this->changePassword();
            } else {
                $this->session->set_flashdata("message", "<div class='alert alert-danger'
                role='alert'>Reset password failed! Wrong token.</div>");
                redirect("auth/forgotpassword");
            }
        } else {
            $this->session->set_flashdata("message", "<div class='alert alert-danger'
            role='alert'>Reset password failed! Wrong email.</div>");
            redirect("auth/forgotpassword");
        }
    }


    public function changePassword()
    {
        if (!$this->session->userdata("reset_email")) {
            redirect("auth");
        }

        $this->form_validation->set_rules("password1", "New Password", "trim|required|min_length[3]", [
            "required"      => "Please insert new password!",
            "min_length"    => "Password too short!"
        ]);
        $this->form_validation->set_rules("password2", "Confirm New Password", "trim|required|matches[password1]", [
            "required"      => "Please confirm new password!",
            "matches"       => "Password dont match!",
        ]);

        if ($this->form_validation->run() == false) {
            $data   = array(
                "page_title"    => "Change Password"
            );
            $this->load->view("templates/auth_header", $data);
            $this->load->view("auth/change-password", $data);
            $this->load->view("templates/auth_footer");
        } else {
            $password   = password_hash($this->input->post("password1"), PASSWORD_DEFAULT);
            $email      = $this->session->userdata("reset_email");

            $this->db->set("password", $password);
            $this->db->where("email", $email);
            $this->db->update("user");

            $this->session->unset_userdata("reset_email");

            $this->session->set_flashdata("message", "<div class='alert alert-success'
            role='alert'>Password has been changed! Please login.</div>");
            redirect("auth");
        }
    }


    public function forgotPassword()
    {
        $this->form_validation->set_rules("email", "Email", "trim|required|valid_email", [
            "required"      => "Please insert your email!"
        ]);

        if ($this->form_validation->run() == false) {
            $data   = array(
                "page_title"    => "Forgot Password"
            );
            $this->load->view("templates/auth_header", $data);
            $this->load->view("auth/forgot-password", $data);
            $this->load->view("templates/auth_footer");
        } else {
            $email  = $this->input->post("email");
            $user   = $this->db->get_where("user", [
                "email"     => $email,
                "is_active" => 1
            ])->row_array();

            if ($user) {
                $token      = base64_encode(random_bytes(32));
                $user_token = array(
                    "email"         => $email,
                    "token"         => $token,
                    "date_created"  => time(),
                );

                $this->db->insert("user_token", $user_token);
                $this->_sendEmail($token, "forgot");

                $this->session->set_flashdata("message", "<div class='alert alert-success'
                role='alert'>Please check your email to reset your password!</div>");
                redirect("auth/forgotpassword");
            } else {
                $this->session->set_flashdata("message", "<div class='alert alert-danger'
                role='alert'>Email is not registered or activated!</div>");
                redirect("auth/forgotpassword");
            }
        }
    }


    public function logout()
    {
        $this->session->unset_userdata("email");
        $this->session->unset_userdata("role_id");

        $this->session->set_flashdata("message", "<div class='alert alert-success'
        role='alert'>You have been logged out!</div>");
        redirect("auth");
    }


    public function blocked()
    {
        $data   = array(
            "page_title"    => "Access blocked"
        );

        $this->load->view("auth/blocked", $data);
    }
}

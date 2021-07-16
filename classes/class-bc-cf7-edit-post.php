<?php

if(!class_exists('BC_CF7_Edit_Post')){
    final class BC_CF7_Edit_Post {

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private static $instance = null;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public static function get_instance($file = ''){
            if(null !== self::$instance){
                return self::$instance;
            }
            if('' === $file){
                wp_die(__('File doesn&#8217;t exist?'));
            }
            if(!is_file($file)){
                wp_die(sprintf(__('File &#8220;%s&#8221; doesn&#8217;t exist?'), $file));
            }
            self::$instance = new self($file);
            return self::$instance;
    	}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private $additional_data = [], $file = '', $post_id = 0;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __clone(){}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __construct($file = ''){
            $this->file = $file;
            add_action('plugins_loaded', [$this, 'plugins_loaded']);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private function get_post_id($contact_form = null, $submission = null){
            if(null === $contact_form){
                return new WP_Error('bc_error', __('The requested contact form was not found.', 'contact-form-7'));
            }
            $type = $contact_form->pref('bc_type');
            if(null === $type){
                return new WP_Error('bc_error', sprintf(__('Missing parameter(s): %s'), 'bc_type') . '.');
            }
            if($type !== 'edit-post'){
                return new WP_Error('bc_error', sprintf(__('%1$s is not of type %2$s.'), $type, 'edit-post'));
            }
            $missing = [];
            if(null === $submission){
                $nonce = null;
                $post_id = $contact_form->shortcode_attr('bc_post_id');
            } else {
                $nonce = $submission->get_posted_data('bc_nonce');
                if(null === $nonce){
                    $missing[] = 'bc_nonce';
                }
                $post_id = $submission->get_posted_data('bc_post_id');
            }
            if(null === $post_id){
                $missing[] = 'bc_post_id';
            }
            if($missing){
                return new WP_Error('bc_error', sprintf(__('Missing parameter(s): %s'), implode(', ', $missing)) . '.');
            }
            if(null !== $nonce and !wp_verify_nonce($nonce, 'bc-edit-post_' . $post_id)){
                $message = __('The link you followed has expired.');
                $message .=  ' ' . bc_last_p(__('An error has occurred. Please reload the page and try again.'));
                return new WP_Error('bc_error', $message);
            }
            $post_id = $this->sanitize_post_id($post_id);
            if(0 === $post_id){
                return new WP_Error('bc_error', __('Invalid post ID.'));
            }
            if(!current_user_can('edit_post', $post_id)){
                if('post' === get_post_type($post_id)){
                    $message = __('Sorry, you are not allowed to edit this post.');
                } else {
                    $message = __('Sorry, you are not allowed to edit this item.');
                }
                $message .=  ' ' . __('You need a higher level of permission.');
                return new WP_Error('bc_error', $message);
			}
            if('trash' === get_post_status($post_id)){
                return new WP_Error('bc_error', __('You can&#8217;t edit this item because it is in the Trash. Please restore it and try again.'));
            }
            return $post_id;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function output($post_id, $attr, $content, $tag){
            global $post;
            $post = get_post($post_id);
            setup_postdata($post);
            $output = wpcf7_contact_form_tag_func($attr, $content, $tag);
            wp_reset_postdata();
            return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function sanitize_post_id($post_id){
            $post = null;
            if(is_numeric($post_id)){
                $post = get_post($post_id);
            } else {
                if('current' === $post_id){
                    if(in_the_loop()){
                        $post = get_post();
                    }
                }
            }
            if(null === $post){
                return 0;
            }
            return $post->ID;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function upload_file($tmp_name = '', $post_id = 0){
            $file = bc_move_uploaded_file($tmp_name);
            if(is_wp_error($file)){
                return $file;
            }
            return bc_upload($file, $post_id);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_free_text_value($value, $tag){
            if('' !== $value){
                return $value;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if('edit-post' !== bc_cf7_type($contact_form)){
                return $value;
            }
            $post_id = $this->get_post_id($contact_form);
            if(is_wp_error($post_id)){
                return $value;
            }
            return get_post_meta($post_id, $tag->name . '_free_text', true);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function do_shortcode_tag($output, $tag, $attr, $m){
			if('contact-form-7' !== $tag){
                return $output;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if('edit-post' !== bc_cf7_type($contact_form)){
                return $output;
            }
            $post_id = $this->get_post_id($contact_form);
            if(is_wp_error($post_id)){
                return '<div class="alert alert-danger" role="alert">' . $post_id->get_error_message() . '</div>';
            }
            $content = isset($m[5]) ? $m[5] : null;
            $output = $this->output($post_id, $attr, $content, $tag);
            return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function plugins_loaded(){
            if(!defined('BC_FUNCTIONS')){
        		return;
        	}
            if(!defined('WPCF7_VERSION')){
        		return;
        	}
            add_action('wpcf7_before_send_mail', [$this, 'wpcf7_before_send_mail'], 10, 3);
            add_filter('bc_cf7_free_text_value', [$this, 'bc_cf7_free_text_value'], 10, 2);
            add_filter('do_shortcode_tag', [$this, 'do_shortcode_tag'], 10, 4);
            add_filter('shortcode_atts_wpcf7', [$this, 'shortcode_atts_wpcf7'], 10, 3);
            add_filter('wpcf7_feedback_response', [$this, 'wpcf7_feedback_response'], 15, 2);
            add_filter('wpcf7_form_hidden_fields', [$this, 'wpcf7_form_hidden_fields'], 15);
            add_filter('wpcf7_posted_data', [$this, 'wpcf7_posted_data']);
            add_filter('wpcf7_posted_data_checkbox', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_checkbox*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_radio', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_radio*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_select', [$this, 'wpcf7_posted_data_type'], 10, 3);
            add_filter('wpcf7_posted_data_select*', [$this, 'wpcf7_posted_data_type'], 10, 3);
            if(!has_filter('wpcf7_verify_nonce', 'is_user_logged_in')){
                add_filter('wpcf7_verify_nonce', 'is_user_logged_in');
            }
            bc_build_update_checker('https://github.com/beavercoffee/bc-cf7-edit-post', $this->file, 'bc-cf7-edit-post');
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function shortcode_atts_wpcf7($out, $pairs, $atts){
            if(isset($atts['bc_post_id'])){
                $out['bc_post_id'] = $atts['bc_post_id'];
            }
            return $out;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_before_send_mail($contact_form, &$abort, $submission){
            if('edit-post' !== bc_cf7_type($contact_form)){
                return;
            }
            if(!$submission->is('init')){
                return; // prevent conflicts with other plugins
            }
            $abort = true; // prevent mail_sent and mail_failed actions
            $post_id = $this->get_post_id($contact_form, $submission);
            if(is_wp_error($post_id)){
                $submission->set_response($post_id->get_error_message());
                $submission->set_status('aborted'); // try to prevent conflicts with other plugins
                return;
            }
            $this->post_id = $post_id;
            $response = 'post' === get_post_type($post_id) ? __('Post updated.') : __('Item updated.');
            if(bc_cf7_skip_mail($contact_form)){
                $submission->set_response($response);
                $submission->set_status('mail_sent');
            } else {
                if(bc_cf7_mail($contact_form)){
                    $submission->set_response($response . ' ' . $contact_form->message('mail_sent_ok'));
                    $submission->set_status('mail_sent');
                } else {
                    $submission->set_response($response . ' ' . $contact_form->message('mail_sent_ng'));
                    $submission->set_status('mail_failed');
                }
            }
            bc_cf7_update_meta_data(bc_cf7_meta_data($contact_form, $submission), $post_id);
            bc_cf7_update_posted_data($submission->get_posted_data(), $post_id);
            bc_cf7_update_uploaded_files($submission->uploaded_files(), $post_id);
            do_action('bc_cf7_edit_post', $post_id, $contact_form, $submission);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_feedback_response($response, $result){
            if(0 !== $this->post_id){
                if(isset($response['bc_uniqid']) and '' !== $response['bc_uniqid']){
                    $uniqid = get_post_meta($this->post_id, 'bc_uniqid', true);
                    if('' !== $uniqid){
                        $response['bc_uniqid'] = $uniqid;
                    }
                }
            }
            return $response;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_form_hidden_fields($hidden_fields){
            $contact_form = wpcf7_get_current_contact_form();
            if('edit-post' !== bc_cf7_type($contact_form)){
                return $hidden_fields;
            }
            $post_id = $this->get_post_id($contact_form);
            if(is_wp_error($post_id)){
                return $hidden_fields;
            }
            $hidden_fields['bc_post_id'] = $post_id;
            $hidden_fields['bc_nonce'] = wp_create_nonce('bc-edit-post_' . $post_id);
            if(isset($hidden_fields['bc_uniqid'])){
                $uniqid = get_post_meta($post_id, 'bc_uniqid', true);
                if('' !== $uniqid){
                    $hidden_fields['bc_uniqid'] = $uniqid;
                }
            }
            return $hidden_fields;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_posted_data($posted_data){
            if($this->additional_data){
                $posted_data = array_merge($posted_data, $this->additional_data);
            }
            return $posted_data;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_posted_data_type($value, $value_orig, $tag){
			$name = $tag->name;
            $pipes = $tag->pipes;
            $type = $tag->type;
			if(wpcf7_form_tag_supports($type, 'selectable-values')){
                $value = (array) $value;
                $value_orig = (array) $value_orig;
				if($tag->has_option('free_text') and isset($_POST[$name . '_free_text'])){
        			$last_val = array_pop($value);
					list($tied_item) = array_slice(WPCF7_USE_PIPE ? $tag->pipes->collect_afters() : $tag->values, -1, 1);
					$tied_item = html_entity_decode($tied_item, ENT_QUOTES, 'UTF-8');
        			if($last_val === $tied_item){
        				$value[] = $last_val;
                        $this->additional_data[$name . '_free_text'] = '';
        			} else {
        				$value[] = $tied_item;
        				$this->additional_data[$name . '_free_text'] = trim(str_replace($tied_item, '', $last_val));
        			}
                }
            }
			if(WPCF7_USE_PIPE and $pipes instanceof WPCF7_Pipes and !$pipes->zero()){
				$this->additional_data[$name . '_value'] = $value;
				$value = $value_orig;
            }
            return $value;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}

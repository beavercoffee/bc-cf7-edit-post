<?php

if(!class_exists('BC_CF7_Edit_Post')){
    final class BC_CF7_Edit_Post {

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private static $instance = null, $post_id = 0;

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

        private $file = '';

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __clone(){}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __construct($file = ''){
            $this->file = $file;
            add_action('plugins_loaded', [$this, 'plugins_loaded']);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function copy($source = '', $destination = '', $overwrite = false, $mode = false){
            global $wp_filesystem;
            $fs = $this->filesystem();
            if(is_wp_error($fs)){
                return $fs;
            }
            if(!$wp_filesystem->copy($source, $destination, $overwrite)){
                return new WP_Error('files_not_writable', sprintf(__('The uploaded file could not be moved to %s.'), $destination));
            }
            return $destination;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function filesystem(){
            global $wp_filesystem;
            if($wp_filesystem instanceof WP_Filesystem_Direct){
                return true;
            }
            if(!function_exists('get_filesystem_method')){
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            if('direct' !== get_filesystem_method()){
                return new WP_Error('fs_unavailable', __('Could not access filesystem.'));
            }
            if(!WP_Filesystem()){
                return new WP_Error('fs_error', __('Filesystem error.'));
            }
            return true;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function first_p($text = '', $dot = true){
            return $this->one_p($text, $dot, 'first');
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
                    $missing['bc_nonce'];
                }
                $post_id = $submission->get_posted_data('bc_post_id');
            }
            if(null === $post_id){
                $missing['bc_post_id'];
            }
            if($missing){
                return new WP_Error('bc_error', sprintf(__('Missing parameter(s): %s'), implode(', ', $missing)) . '.');
            }
            if(null !== $nonce and !wp_verify_nonce($nonce, 'bc-edit-post_' . $post_id)){
                $message = __('The link you followed has expired.');
                $message .=  ' ' . $this->last_p(__('An error has occurred. Please reload the page and try again.'));
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

    	private function get_type($contact_form = null){
            if(null === $contact_form){
                $contact_form = wpcf7_get_current_contact_form();
            }
            if(null === $contact_form){
                return '';
            }
            $type = $contact_form->pref('bc_type');
            if(null === $type){
                return '';
            }
            return $type;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function last_p($text = '', $dot = true){
            return $this->one_p($text, $dot, 'last');
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function one_p($text = '', $dot = true, $p = 'first'){
            if(false === strpos($text, '.')){
                if($dot){
                    $text .= '.';
                }
                return $text;
            } else {
                $text = explode('.', $text);
				$text = array_filter($text);
                switch($p){
                    case 'first':
                        $text = array_shift($text);
                        break;
                    case 'last':
                        $text = array_pop($text);
                        break;
                    default:
                        $text = __('Error');
                }
                if($dot){
                    $text .= '.';
                }
                return $text;
            }
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
                    $post = get_post();
                }
            }
            if(null === $post){
                return 0;
            }
            return $post->ID;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function upload_file($tmp_name = '', $post_id = 0){
            global $wp_filesystem;
            $upload_dir = wp_upload_dir();
            $original_filename = wp_basename($tmp_name);
            $filename = wp_unique_filename($upload_dir['path'], $original_filename);
            $file = trailingslashit($upload_dir['path']) . $filename;
            $result = $this->copy($tmp_name, $file);
            if(is_wp_error($result)){
                return $result;
            }
            $filetype_and_ext = wp_check_filetype_and_ext($file, $filename);
            if(!$filetype_and_ext['type']){
                return new WP_Error('invalid_filetype', __('Sorry, this file type is not permitted for security reasons.'));
            }
            $attachment_id = wp_insert_attachment([
                'guid' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file),
                'post_mime_type' => $filetype_and_ext['type'],
                'post_status' => 'inherit',
                'post_title' => preg_replace('/\.[^.]+$/', '', $original_filename),
            ], $file, $post_id, true);
            if(is_wp_error($attachment_id)){
                return $attachment_id;
            }
            $attachment = get_post($attachment_id);
            wp_raise_memory_limit('image');
            wp_maybe_generate_attachment_metadata($attachment);
            return $attachment_id;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function do_shortcode_tag($output, $tag, $attr, $m){
			if('contact-form-7' !== $tag){
                return $output;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if('edit-post' !== $this->get_type($contact_form)){
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
            if(!defined('WPCF7_VERSION')){
        		return;
        	}
            add_filter('do_shortcode_tag', [$this, 'do_shortcode_tag'], 10, 4);
            add_filter('shortcode_atts_wpcf7', [$this, 'shortcode_atts_wpcf7'], 10, 3);
            add_action('wpcf7_before_send_mail', [$this, 'wpcf7_before_send_mail'], 10, 3);
            add_filter('wpcf7_feedback_response', [$this, 'wpcf7_feedback_response'], 15, 2);
            add_filter('wpcf7_form_hidden_fields', [$this, 'wpcf7_form_hidden_fields'], 15);
            if(!has_filter('wpcf7_verify_nonce', 'is_user_logged_in')){
                add_filter('wpcf7_verify_nonce', 'is_user_logged_in');
            }
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
            if('edit-post' !== $this->get_type($contact_form)){
                return;
            }
            if(!$submission->is('init')){
                return;
            }
            $abort = true;
            $post_id = $this->get_post_id($contact_form, $submission);
            if(is_wp_error($post_id)){
                $submission->set_response($post_id->get_error_message());
            }
            $this->post_id = $post_id;
            foreach($submission->get_posted_data() as $key => $value){
                if(is_array($value)){
					delete_post_meta($post_id, $key);
					foreach($value as $single){
						add_post_meta($post_id, $key, $single);
					}
				} else {
                    update_post_meta($post_id, $key, $value);
				}
			}
            $error = new WP_Error;
            $uploaded_files = $submission->uploaded_files();
            if($uploaded_files){
                foreach($uploaded_files as $key => $value){
                    delete_post_meta($post_id, $key . '_id');
                    delete_post_meta($post_id, $key . '_filename');
                    foreach((array) $value as $single){
                        $attachment_id = $this->upload_file($single, $post_id);
                        if(is_wp_error($attachment_id)){
                            $error->merge_from($attachment_id);
                        } else {
                            add_post_meta($post_id, $key . '_id', $attachment_id);
                            add_post_meta($post_id, $key . '_filename', wp_basename($single));
                        }
                    }
                }
            }
            do_action('bc_cf7_edit_post', $post_id, $contact_form, $error);
            if($error->has_errors()){
                $message = $error->get_error_message();
                $message .=  ' ' . $this->last_p(__('Application passwords are not available for your account. Please contact the site administrator for assistance.'));
                $submission->set_response($message);
                $submission->set_status('aborted');
                return;
            }
            if('post' === get_post_type($post_id)){
                $submission->set_response(__('Post updated.'));
            } else {
                $submission->set_response(__('Item updated.'));
            }
            $submission->set_status('wpcf7_mail_sent');
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_feedback_response($response, $result){
            if(isset($response['bc_uniqid']) and '' !== $response['bc_uniqid']){
                if(0 !== $this->post_id){
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
            if('edit-post' !== $this->get_type($contact_form)){
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

    }
}

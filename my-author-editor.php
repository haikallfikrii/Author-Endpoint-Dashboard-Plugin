<?php
/*
Plugin Name: My Author Editor
Plugin URI:  https://example.com/
Description: A custom editor for author profiles, accessible via shortcode.
Version:     1.0.3 // Updated version for layout fixes
Author:      Muhamad Fikri Haikal
Author URI:  https://caastedu.com/
License:     GPL2
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Global flag to indicate if the shortcode is used on the current page
global $my_author_editor_shortcode_used;
$my_author_editor_shortcode_used = false;

// Define plugin constants if not already defined
if ( ! defined( 'MY_AUTHOR_EDITOR_VERSION' ) ) {
    define( 'MY_AUTHOR_EDITOR_VERSION', '1.0.3' ); // Updated version
}
if ( ! defined( 'MY_AUTHOR_EDITOR_PLUGIN_URL' ) ) {
    define( 'MY_AUTHOR_EDITOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'MY_AUTHOR_EDITOR_PLUGIN_DIR' ) ) {
    define( 'MY_AUTHOR_EDITOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Register Custom Post Type for Author Content
 * This is highly recommended instead of using default 'post' type.
 */
function my_author_editor_register_cpt() {
    $labels = array(
        'name'          => _x( 'Author Contents', 'Post Type General Name', 'my-author-editor' ),
        'singular_name' => _x( 'Author Content', 'Post Type Singular Name', 'my-author-editor' ),
        'menu_name'     => __( 'Author Content', 'my-author-editor' ),
        'all_items'     => __( 'All Author Contents', 'my-author-editor' ),
        'add_new_item'  => __( 'Add New Author Content', 'my-author-editor' ),
        'add_new'       => __( 'Add New', 'my-author-editor' ),
        'new_item'      => __( 'New Author Content', 'my-author-editor' ),
        'edit_item'     => __( 'Edit Author Content', 'my-author-editor' ),
        'update_item'   => __( 'Update Author Content', 'my-author-editor' ),
        'view_item'     => __( 'View Author Content', 'my-author-editor' ),
        'search_items'  => __( 'Search Author Content', 'my-author-editor' ),
        'not_found'     => __( 'Not Found', 'my-author-editor' ),
        'not_found_in_trash' => __( 'Not found in Trash', 'my-author-editor' ),
    );
    $args = array(
        'label'               => __( 'Author Content', 'my-author-editor' ),
        'description'         => __( 'Content associated with specific authors', 'my-author-editor' ),
        'labels'              => $labels,
        'supports'            => array( 'title', 'editor', 'author', 'custom-fields', 'thumbnail' ), // Ensure 'author' and 'thumbnail' support
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 20,
        'menu_icon'           => 'dashicons-admin-post',
        'show_in_admin_bar'   => true,
        'show_in_nav_menus'   => true,
        'can_export'          => true,
        'has_archive'         => true,
        'exclude_from_search' => false,
        'publicly_queryable'  => true,
        'capability_type'     => 'post',
        'rewrite'             => array( 'slug' => 'author-content-profile' ), // URL slug for your CPT
        'query_var'           => true,
    );
    register_post_type( 'author_profile', $args ); // Registered as 'author_profile'
}
add_action( 'init', 'my_author_editor_register_cpt' );

// Register custom post status 'archive'
function my_author_editor_custom_post_status() {
    register_post_status( 'archive', array(
        'label'                     => _x( 'Archive', 'Post status', 'my-author-editor' ),
        'public'                    => true, // Set to false if you don't want it publicly viewable
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Archive <span class="count">(%s)</span>', 'Archive <span class="count">(%s)</span>', 'my-author-editor' ),
    ) );
}
add_action( 'init', 'my_author_editor_custom_post_status' );


/**
 * Enqueue styles and scripts for the author editor.
 * This will only run if the shortcode is detected on the page.
 */
function my_author_editor_enqueue_assets() {
    global $my_author_editor_shortcode_used;

    if ( $my_author_editor_shortcode_used ) {
        // Enqueue media uploader scripts. This is ESSENTIAL for the 'Browse' buttons to work.
        wp_enqueue_media();

        // Enqueue TinyMCE and other editor-related scripts if wp_editor() is used on frontend
        wp_enqueue_script('wp-tinymce'); // The core TinyMCE script
        wp_enqueue_script('editor');     // WordPress editor integration script
        wp_enqueue_script('wplink');     // For link popup
        wp_enqueue_script('wp-plupload'); // For image uploads

        // Enqueue the plugin's specific JavaScript with necessary dependencies for wp.media
        wp_enqueue_script(
            'my-author-editor-script',
            MY_AUTHOR_EDITOR_PLUGIN_URL . 'js/my-author-editor.js',
            array( 'jquery', 'wp-tinymce', 'editor', 'media-upload', 'thickbox' ),
            MY_AUTHOR_EDITOR_VERSION,
            true
        );
        wp_enqueue_style( 'thickbox' ); // Thickbox CSS also needed for media uploader UI

        // Localize AJAX URL and nonce for JavaScript
        wp_localize_script( 'my-author-editor-script', 'myAuthorEditorAjax', array(
            'ajaxurl'                 => admin_url( 'admin-ajax.php' ), // Default AJAX URL
            'nonce'                   => wp_create_nonce( 'author_editor_nonce' ), // Specific nonce for this form
            'alert_no_author_selected' => __( 'Please select an author first.', 'my-author-editor' ),
            'alert_confirm_delete'     => __( 'Are you sure you want to delete this content? This action cannot be undone.', 'my-author-editor' ),
            'alert_content_not_found'  => __( 'Content not found for the selected author or status.', 'my-author-editor' ),
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'my_author_editor_enqueue_assets' );

/**
 * Handle form submission for Author Content Editor.
 * This uses the admin-post.php handler for robustness.
 */
function my_author_editor_handle_submission() {
    if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'my_author_editor_submit' ) {
        return; // Not our form submission
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
        wp_safe_redirect( home_url() );
        exit;
    }

    // Verify nonce
    if ( ! isset( $_POST['_wpnonce_author_content'] ) || ! wp_verify_nonce( $_POST['_wpnonce_author_content'], 'author_content_submission' ) ) {
        wp_die( 'Security check failed. Please refresh the page and try again.' );
    }

    // Sanitize and retrieve form data
    $selected_author_id = isset( $_POST['selected_author_id'] ) ? intval( $_POST['selected_author_id'] ) : 0; // The ID from the new dropdown
    $new_author_name    = sanitize_text_field( $_POST['new_author_name'] ); // New author name field
    $author_subtitle    = sanitize_text_field( $_POST['author_subtitle'] );
    $author_content     = wp_kses_post( $_POST['author_content'] ); // Content from TinyMCE
    $author_photo_id    = isset( $_POST['author_photo_id'] ) ? intval( $_POST['author_photo_id'] ) : 0;
    // Removed other_image_id
    $footer_position    = isset( $_POST['footer_position'] ) ? sanitize_text_field( $_POST['footer_position'] ) : ''; // New field: footer position
    $insert_link        = isset( $_POST['insert_link'] ) ? esc_url_raw( $_POST['insert_link'] ) : ''; // New field: insert link
    $submit_action      = sanitize_text_field( $_POST['submit_author_action'] ); // The value from the clicked button

    // Determine the actual author ID to use for the post
    $post_author_id = $selected_author_id;
    $author_display_name = '';

    if ( ! empty( $new_author_name ) ) {
        // Create new user if a new author name is provided
        $username = sanitize_user( str_replace( ' ', '', strtolower( $new_author_name ) ) );
        // Use a more robust way to generate unique email if not provided by user
        $email = $username . '_' . time() . '@example.com'; // Placeholder email

        if ( ! username_exists( $username ) ) { // Only check username, email check below
            $new_user_id = wp_create_user( $username, wp_generate_password( 12, false ), $email );

            if ( ! is_wp_error( $new_user_id ) ) {
                // Set role, e.g., 'author'
                $user = new WP_User( $new_user_id );
                $user->set_role( 'author' );
                wp_update_user( array( 'ID' => $new_user_id, 'display_name' => $new_author_name ) ); // Set display name
                $post_author_id = $new_user_id;
                $author_display_name = $new_author_name; // Use the provided new name as display name
            } else {
                // Error creating user, fall back or show error
                error_log( 'Error creating new author user: ' . $new_user_id->get_error_message() );
                wp_safe_redirect( add_query_arg( 'status', 'error_create_author', wp_get_referer() ) );
                exit;
            }
        } else {
            // User with similar username exists, fallback or show error
            error_log( 'New author username already exists: ' . $username );
            wp_safe_redirect( add_query_arg( 'status', 'error_author_exists', wp_get_referer() ) );
            exit;
        }
    } else if ( $selected_author_id > 0 ) {
        $author_display_name = get_the_author_meta( 'display_name', $selected_author_id );
    }


    // Get the post ID if it exists, for updating.
    // This is crucial for editing existing entries.
    $existing_post_id = isset( $_POST['author_post_id'] ) ? intval( $_POST['author_post_id'] ) : 0;

    // Basic validation
    if ( empty( $author_display_name ) || empty( $author_content ) ) {
        wp_safe_redirect( add_query_arg( 'status', 'error_empty_fields', wp_get_referer() ) );
        exit;
    }

    // Determine post status based on button clicked
    $post_status = 'pending'; // Default if not publish/save
    $redirect_status = 'error'; // Default redirect status

    if ( $submit_action === 'publish' ) {
        if ( current_user_can( 'publish_posts' ) ) {
            $post_status = 'publish';
            $redirect_status = 'author_published';
        } else {
            $post_status = 'pending'; // Fallback if user can't publish
            $redirect_status = 'author_pending_review';
        }
    } elseif ( $submit_action === 'save' ) {
        $post_status = 'draft';
        $redirect_status = 'author_saved_draft';
    } elseif ( $submit_action === 'unpublish' ) {
        $post_status = 'draft'; // Change status to draft for unpublish
        $redirect_status = 'author_unpublished';
    } elseif ( $submit_action === 'archive' ) {
        $post_status = 'archive'; // Use custom status 'archive'
        $redirect_status = 'author_archived';
    } elseif ( $submit_action === 'delete' ) {
        if ( $existing_post_id ) {
            if ( wp_delete_post( $existing_post_id, true ) ) { // true for force delete
                wp_safe_redirect( add_query_arg( 'status', 'author_deleted', wp_get_referer() ) );
                exit;
            } else {
                error_log( 'Error deleting author post: ' . $existing_post_id );
                wp_safe_redirect( add_query_arg( 'status', 'error_author_delete', wp_get_referer() ) );
                exit;
            }
        } else {
            wp_safe_redirect( add_query_arg( 'status', 'error_no_content_to_delete', wp_get_referer() ) );
            exit;
        }
    }

    // Prepare post data
    $post_data = array(
        'post_title'    => $author_display_name, // Use the display name derived from selected or new author
        'post_content'  => $author_content,
        'post_status'   => $post_status,
        'post_type'     => 'author_profile', // Use your custom post type
        'post_author'   => $post_author_id, // The selected or newly created author ID
    );

    $post_id = 0;
    if ( $existing_post_id > 0 ) {
        // Update existing post
        $post_data['ID'] = $existing_post_id;
        $result = wp_update_post( $post_data, true );
    } else {
        // Insert new post
        $result = wp_insert_post( $post_data, true );
    }

    if ( is_wp_error( $result ) ) {
        error_log( 'Error creating/updating author post: ' . $result->get_error_message() );
        wp_safe_redirect( add_query_arg( 'status', 'error_author_db', wp_get_referer() ) );
    } elseif ( $result === 0 ) {
        error_log( 'Failed to create/update author post, wp_insert_post/wp_update_post returned 0.' );
        wp_safe_redirect( add_query_arg( 'status', 'error_author_db', wp_get_referer() ) );
    } else {
        // Save author subtitle as post meta
        update_post_meta( $result, '_author_subtitle', $author_subtitle );

        // Set featured image (Author Photo)
        if ( ! empty( $author_photo_id ) ) {
            set_post_thumbnail( $result, $author_photo_id );
        } else {
            delete_post_thumbnail( $result ); // Remove if ID is empty
        }

        // Save new footer fields as post meta
        update_post_meta( $result, '_footer_position', $footer_position );
        update_post_meta( $result, '_insert_link', $insert_link );

        wp_safe_redirect( add_query_arg( 'status', $redirect_status, wp_get_referer() ) );
    }
    exit; // Always exit after redirect
}
add_action( 'admin_post_my_author_editor_submit', 'my_author_editor_handle_submission' );
add_action( 'admin_post_nopriv_my_author_editor_submit', 'my_author_editor_handle_submission' );

/**
 * AJAX handler to get author content based on author ID.
 */
function my_author_editor_get_author_content_ajax() {
    check_ajax_referer( 'author_editor_nonce', 'nonce' );

    $author_id = isset( $_POST['author_id'] ) ? intval( $_POST['author_id'] ) : 0;

    if ( $author_id ) {
        $args = array(
            'author'         => $author_id,
            'post_type'      => 'author_profile', // Use your custom post type
            'post_status'    => array( 'publish', 'pending', 'draft', 'archive' ),
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        $author_posts_query = new WP_Query( $args );

        if ( $author_posts_query->have_posts() ) {
            $author_post = $author_posts_query->posts[0];
            wp_send_json_success( array(
                'author_post_id'       => $author_post->ID,
                'author_name'          => esc_html( $author_post->post_title ), // This is the post title
                'author_subtitle'      => esc_html( get_post_meta( $author_post->ID, '_author_subtitle', true ) ),
                'author_content'       => $author_post->post_content,
                'author_photo_id'      => get_post_thumbnail_id( $author_post->ID ),
                'author_photo_url'     => get_post_thumbnail_id( $author_post->ID ) ? wp_get_attachment_url( get_post_thumbnail_id( $author_post->ID ) ) : '',
                // Removed other_image_id and url
                'footer_position'      => esc_html( get_post_meta( $author_post->ID, '_footer_position', true ) ), // New meta field
                'insert_link'          => esc_url( get_post_meta( $author_post->ID, '_insert_link', true ) ), // New meta field
            ) );
        } else {
            // No content found, return empty fields to allow new creation for this author
            wp_send_json_success( array(
                'author_post_id'       => 0,
                'author_name'          => get_the_author_meta( 'display_name', $author_id ), // Default to actual author name if no content exists
                'author_subtitle'      => '',
                'author_content'       => '',
                'author_photo_id'      => 0,
                'author_photo_url'     => '',
                // Removed other_image_id and url
                'footer_position'      => '', // Default empty
                'insert_link'          => '', // Default empty
                'message'              => 'No existing content found for this author. You can create a new one.',
            ) );
        }
    } else {
        wp_send_json_error( array( 'message' => __( 'Invalid author ID.', 'my-author-editor' ) ) );
    }
}
add_action( 'wp_ajax_my_author_editor_get_author_content', 'my_author_editor_get_author_content_ajax' );
add_action( 'wp_ajax_nopriv_my_author_editor_get_author_content', 'my_author_editor_get_author_content_ajax' ); // If non-logged-in users can load content

/**
 * Shortcode to display the Author Content Editor.
 */
function my_author_editor_shortcode() {
    global $my_author_editor_shortcode_used;
    $my_author_editor_shortcode_used = true; // Set flag to true so assets are enqueued

    ob_start();

    // Check if user is logged in and has capabilities.
    if (!is_user_in_role('author') && !current_user_can('edit_posts')) {
        ?>
        <p class="text-center text-red-500">You must be logged in with sufficient permissions to access the Author Content Editor.</p>
        <?php
        return ob_get_clean();
    }

    // Initialize values for form fields - they will be loaded dynamically by JS or remain empty for new content
    $author_name_val      = ''; // Will be populated by JS based on selected author or new author name
    $author_subtitle_val  = '';
    $author_content_val   = '';
    $author_photo_id_val  = 0;
    $author_photo_url_val = '';
    // Removed other_image_id_val and other_image_url_val
    $footer_position_val  = ''; // New field
    $insert_link_val      = ''; // New field
    $author_post_id_val   = 0; // Hidden field for the current post ID

    // Display status messages
    if ( isset( $_GET['status'] ) ) {
        echo '<div class="notice ';
        if ( in_array( $_GET['status'], ['author_published', 'author_saved_draft', 'author_pending_review', 'author_unpublished', 'author_archived', 'author_deleted', 'author_added'] ) ) {
            echo 'notice-success bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-none relative mb-4';
        } elseif ( in_array( $_GET['status'], ['error_empty_fields', 'error_author_db', 'error_author_delete', 'error_no_content_to_delete', 'error_create_author', 'error_author_exists'] ) ) {
            echo 'notice-error bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-none relative mb-4';
        }
        echo ' is-dismissible"><p>';
        switch ( $_GET['status'] ) {
            case 'author_published':
                echo 'Author article successfully published!'; break;
            case 'author_saved_draft':
                echo 'Author article saved as draft!'; break;
            case 'author_pending_review':
                echo 'Author article submitted for review!'; break;
            case 'author_unpublished':
                echo 'Author article successfully unpublished (set to draft)!'; break;
            case 'author_archived':
                echo 'Author article successfully archived!'; break;
            case 'author_deleted':
                echo 'Author article successfully deleted!'; break;
            case 'author_added':
                echo 'New author added successfully!'; break;
            case 'error_empty_fields':
                echo 'Error: Author name and content cannot be empty. Please fill all required fields.'; break;
            case 'error_author_db':
                echo 'An error occurred while submitting the author article. Please try again.'; break;
            case 'error_author_delete':
                echo 'An error occurred while deleting the author article. Please try again.'; break;
            case 'error_no_content_to_delete':
                echo 'No content was selected for deletion.'; break;
            case 'error_create_author':
                echo 'Error: Failed to create new author user. Please check server logs.'; break;
            case 'error_author_exists':
                echo 'Error: Author with this name already exists. Please choose a different name or select from the list.'; break;
            default:
                echo 'An unknown status occurred.';
        }
        echo '</p></div>';
    }

    // Replicate the layout from my-custom-dashboard.php and page-author-endpoint.php
    ?>
    <div class="bg-gray-100 text-[12px] text-gray-600 font-mono flex justify-end gap-9 px-20 py-1 max-w-12xl mx-auto">
        <span>myEMAIL</span>
        <span>myCALENDAR</span>
        <span>myPROJECTS</span>
        <span>myLEARNING</span>
        <span>myWORK</span>
        <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="text-gray-600 hover:text-black">Logout</a>
    </div>

    <main class="w-full mx-auto flex flex-col md:flex-row gap-2 px-2 py-2 min-h-[118vh]">
        <aside aria-label="Left admin navigation panel" class="w-[35px] md:w-auto border border-gray-300 rounded-none p-3 text-xs text-gray-700 font-sans bg-white flex-shrink-0 min-h-[118vh] overflow-y-auto hide-scrollbar">
            <div class="flex items-center gap-2 mb-4">
                <img alt="User avatar placeholder" class="rounded-full w-6 h-6" src="https://storage.googleapis.com/a1aa/image/2284957a-9c5a-4c99-b75c-ed6110b26f73.jpg" />
                <span class="truncate text-[11px] text-gray-600"><?php echo esc_html(wp_get_current_user()->user_email); ?></span>
                <i class="fas fa-sync-alt cursor-pointer text-gray-400 text-[12px] ml-auto"></i>
            </div>
            <nav class="space-y-0.5">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Website Content Editor</h2>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/author-endpoint-v2/"><i class="fas fa-user text-gray-500"></i> Author Endpoint Page</a>
                <a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/article-endpoint/"><span><i class="fas fa-file-alt text-gray-500"></i> Article Endpoint Page</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/book-endpoint/"><i class="fas fa-book-open text-gray-500"></i> Book Endpoint Page</a>
                <a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/video-endpoint/"><span><i class="fab fa-youtube text-gray-500"></i> Video Endpoint Page</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>
                <div><br/></div>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/"><i class="fas fa-th-large text-gray-500"></i> Dashboard</a>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/profile/"><i class="fas fa-user text-gray-500"></i> My Profile</a>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/enrolled-courses/"><i class="fas fa-book-open text-gray-500"></i> Enrolled Courses</a>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/my-bookings/"><i class="fas fa-calendar-check text-gray-500"></i> My Tutor Bookings</a>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/download-certificate/"><i class="fas fa-download text-gray-500"></i> Download Certificates</a>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/wishlist/"><i class="far fa-heart text-gray-500"></i> Wishlist</a>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/reviews/"><i class="far fa-star text-gray-500"></i> Reviews</a>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/purchase-history/"><i class="fas fa-history text-gray-500"></i> Purchase History</a>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/my-account/"><span><i class="fas fa-store text-gray-500"></i> Store Dashboard</span></a>
                <a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/courses/"><span><i class="fas fa-book text-gray-500"></i> Courses</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>
                <a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/lessons/"><span><i class="fas fa-file-alt text-gray-500"></i> All Lessons</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>
                <a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/quizzes/"><span><i class="fas fa-question-circle text-gray-500"></i> Quizzes</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>
                <a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/meeting/"><span><i class="fab fa-youtube text-gray-500"></i> Meetings</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>
                <a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/tutor-booking/"><span><i class="fas fa-calendar-check text-gray-500"></i> Tutor Bookings</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>
                <a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/assignments/"><span><i class="fas fa-tasks text-gray-500"></i> Assignments</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>
                <a class="flex items-center gap-2 justify-between hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/question-answer/"><span><i class="fas fa-question text-gray-500"></i> Question &amp; Answer</span><i class="fas fa-chevron-right text-[9px] text-gray-500"></i></a>
                <a class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px]" href="https://caastedu.com/dashboard/announcements/"><i class="fas fa-bullhorn text-gray-500"></i> Announcements</a>
            </nav>
            <div class="mt-4 flex items-center gap-2 text-[11px] text-gray-600 cursor-pointer">
                <a href="https://caastedu.com/dashboard/settings/" class="flex items-center gap-2 hover:bg-gray-50 rounded-none px-2 py-1 text-gray-700 text-[11px] w-full">
                    <i class="fas fa-cog text-gray-500"></i> Settings
                </a>
            </div>
        </aside>

        <section class="flex-1 flex flex-col gap-3">
            <div class="bg-white border border-gray-300 rounded-none p-2 min-h-[calc(118vh - 40px)]">
                <h2 class="text-xl font-semibold mb-4">Author Content Editor</h2>

                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" class="font-sans" id="author-editor-form">
                    <input type="hidden" name="action" value="my_author_editor_submit">
                    <?php wp_nonce_field('author_content_submission', '_wpnonce_author_content'); ?>
                    <input type="hidden" name="author_post_id" id="author_post_id" value="<?php echo esc_attr( $author_post_id_val ); ?>">

                    <div class="grid grid-cols-3 md:grid gap-2 items-end mb-3">
                        <div class="md:col-span-2 flex flex-col">
                            <label for="selected_author_id" class="block text-sm font-medium text-gray-700">Select an author</label>
                            <div class="col-span-2 items-center gap-2">
                                <select name="selected_author_id" id="selected_author_id" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">-- Select an author --</option>
                                    <?php
                                    // Get all users with roles author, editor, administrator
                                    $authors = get_users( array(
                                        'role__in' => array( 'author', 'editor', 'administrator' ),
                                        'orderby'  => 'display_name',
                                        'fields'   => array( 'ID', 'display_name' ),
                                    ) );
                                    foreach ( $authors as $author ) {
                                        echo '<option value="' . esc_attr( $author->ID ) . '">' . esc_html( $author->display_name ) . '</option>';
                                    }
                                    ?>
                                </select>

                            </div>
                        </div>
                                                        <button type="button" id="load-author-content-btn" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Submit</button>
                        <div class="md:col-span-1">
                            </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-3">
                        <div class="col-span-2">
                            <label for="new_author_name" class="block text-sm font-medium text-gray-700">Add an author</label>
                            <input type="text" name="new_author_name" id="new_author_name" placeholder="Enter author name" class="mt-1 mb-3 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="" />
                            
                            <label for="author_subtitle" class="block text-sm font-medium text-gray-700">Author Subtitle:</label>
                            <input type="text" name="author_subtitle" id="author_subtitle" placeholder="Author subtitle text" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_attr($author_subtitle_val); ?>" />
                        </div>

                        <div class="flex flex-col border border-gray-300 rounded-none p-2 bg-white">
                            <label class="block text-sm font-medium text-gray-700">Author Photo:</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="text" id="author_photo_url" name="author_photo_url" class="flex-grow border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Browse author photo" readonly value="<?php echo esc_url($author_photo_url_val); ?>" />
                                <input type="hidden" id="author_photo_id" name="author_photo_id" value="<?php echo esc_attr($author_photo_id_val); ?>" />
                                <button type="button" class="button button-secondary browse-author-image bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Browse</button>
                            </div>
                            <div id="author-image-preview" class="mt-2" style="<?php echo empty($author_photo_url_val) ? 'display:none;' : ''; ?>">
                                <img src="<?php echo esc_url($author_photo_url_val); ?>" alt="Author Photo Preview" style="max-width: 150px; height: auto; display: block; margin: 0 auto;" />
                                <button type="button" class="remove-author-image text-red-500 hover:text-red-700 text-xs mt-1 rounded-none">Remove Image</button>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="author_content" class="block text-sm font-medium text-gray-700">Author Content:</label>
                        <?php
                        wp_editor( $author_content_val, 'author_content', array(
                            'textarea_name' => 'author_content',
                            'textarea_rows' => 12,
                            'teeny'         => false,
                            'media_buttons' => false, // Set to false to hide "Add Media" button
                            'tinymce'       => array(
                                'height' => 200,
                                'toolbar1' => 'undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link | forecolor backcolor',
                                'toolbar2' => 'print preview | table | charmap emoticons | code fullscreen',
                            ),
                            'editor_class' => 'mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm',
                        ) );
                        ?>
                    </div>
                    
                    <div class="grid md:grid-cols-3 justify-start gap-2 mt-2">
                        <label for="submit_author_action_select" class="block text-sm font-medium text-gray-700 sr-only">Choose Action:</label>
                        <select name="submit_author_action" id="submit_author_action_select" class="col-span-2 px-6 py-2 border border-gray-300 rounded-none shadow-sm text-sm font-medium text-gray-700 hover: focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <option value="save">Save Draft</option>
                            <option value="publish">Publish</option>
                            <option value="unpublish">Unpublish</option>
                            <option value="archive">Archive</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button class="px-6 py-2 border border-gray-300 rounded-none shadow-sm text-sm font-medium text-gray-800 bg-gray-200 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" type="submit" id="main-submit-btn">Submit</button>
                    </div>
                    
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mt-4">
                        <div class="flex flex-col">
                            <label for="footer_position" class="block text-sm font-medium text-gray-700">Footer Position:</label>
                            <select name="footer_position" id="footer_position" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">-- Select Position --</option>
                                <?php
                                for ($i = 1; $i <= 12; $i++) {
                                    $value = 'Featured_' . $i;
                                    echo '<option value="' . esc_attr($value) . '" ' . selected($footer_position_val, $value, false) . '>' . esc_html($value) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex flex-col">
                            <label for="insert_link" class="block text-sm font-medium text-gray-700">Insert Link:</label>
                            <input type="url" name="insert_link" id="insert_link" placeholder="https://example.com" class="mt-1 block w-full border border-gray-300 rounded-none shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo esc_url($insert_link_val); ?>" />
                        </div>
                        <div class="flex items-end">
                            <button type="submit" name="submit_author_action" value="save" class="button button-primary bg-gray-200 text-gray-800 px-4 py-2 rounded-none hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 w-full">Submit</button>
                        </div>
                    </div>


                </form>
            </div>
        </section>


    </main>
    <?php
    return ob_get_clean();
}
add_shortcode( 'my_author_editor', 'my_author_editor_shortcode' );

// Helper function to check if a user has a specific role
function is_user_in_role( $role ) {
    $user = wp_get_current_user();
    return in_array( $role, (array) $user->roles );
}
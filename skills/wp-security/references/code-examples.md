# WordPress Security Code Examples

## SQL {#sql}

### Correct patterns

```php
// Integer lookup
$user_id = absint($_GET['id']);
$sql = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}users WHERE id = %d",
    $user_id
);
$user = $wpdb->get_row($sql);

// LIKE search
$keyword = sanitize_text_field($_POST['keyword']);
$sql = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}posts WHERE post_title LIKE %s AND post_status = %s",
    '%' . $wpdb->esc_like($keyword) . '%',
    'publish'
);
$results = $wpdb->get_results($sql);

// Insert (auto-escaped)
$wpdb->insert(
    $wpdb->prefix . 'form_submissions',
    array('name' => $name, 'email' => $email, 'submitted_at' => current_time('mysql')),
    array('%s', '%s', '%s')
);

// Update
$wpdb->update(
    $wpdb->prefix . 'usermeta',
    array('meta_value' => $meta_value),
    array('user_id' => $user_id, 'meta_key' => $meta_key),
    array('%s'),
    array('%d', '%s')
);

// Delete
$wpdb->delete(
    $wpdb->prefix . 'form_submissions',
    array('id' => $id),
    array('%d')
);
```

### What NOT to write

```php
// WRONG — direct interpolation
$sql = "SELECT * FROM {$wpdb->prefix}users WHERE id = $user_id";

// WRONG — string concatenation
$sql = "SELECT * FROM wp_users WHERE id = " . $_GET['id'];

// WRONG — LIKE without esc_like
$sql = $wpdb->prepare("SELECT * FROM wp_posts WHERE title LIKE %s", "%$keyword%");
```

---

## XSS {#xss}

### Correct patterns

```php
// HTML content
echo '<div class="username">' . esc_html($name) . '</div>';

// HTML attribute
echo '<input type="text" value="' . esc_attr($default_value) . '" />';

// URL
echo '<a href="' . esc_url($website) . '">' . esc_html__('Visit', 'textdomain') . '</a>';

// JavaScript string
echo '<script>var userName = "' . esc_js($user_name) . '";</script>';

// Textarea
echo '<textarea name="bio">' . esc_textarea($bio) . '</textarea>';

// Allow safe HTML (post content)
echo wp_kses_post($content);

// Allow custom HTML subset
$allowed = array(
    'a'      => array('href' => array(), 'title' => array()),
    'br'     => array(),
    'em'     => array(),
    'strong' => array(),
);
echo wp_kses($content, $allowed);

// JSON data — preferred: wp_localize_script
wp_localize_script('my-script', 'myData', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('my_nonce'),
));

// JSON inline (fallback)
echo '<script>var settings = ' . wp_json_encode($settings) . ';</script>';
```

---

## CSRF {#csrf}

### Form (POST)

```php
// Generate nonce in form
function render_delete_form($post_id) {
    ?>
    <form method="post">
        <?php wp_nonce_field('delete_post_' . $post_id, 'delete_nonce'); ?>
        <input type="hidden" name="post_id" value="<?php echo absint($post_id); ?>" />
        <button type="submit"><?php esc_html_e('Delete', 'textdomain'); ?></button>
    </form>
    <?php
}

// Verify nonce in handler
function handle_delete() {
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

    if (!wp_verify_nonce($_POST['delete_nonce'] ?? '', 'delete_post_' . $post_id)) {
        wp_die(__('Security check failed', 'textdomain'));
    }

    if (!current_user_can('delete_post', $post_id)) {
        wp_die(__('Permission denied', 'textdomain'));
    }

    wp_delete_post($post_id, true);
    wp_redirect(admin_url('edit.php'));
    exit;
}
add_action('admin_init', 'handle_delete');
```

### GET link

```php
// Generate nonce URL
$url = wp_nonce_url(
    add_query_arg(array('action' => 'delete_post', 'post_id' => $post_id), admin_url('admin.php')),
    'delete_post_' . $post_id,
    'nonce'
);

// Verify
if (!wp_verify_nonce($_GET['nonce'] ?? '', 'delete_post_' . absint($_GET['post_id']))) {
    wp_die(__('Security check failed', 'textdomain'));
}
```

### AJAX

```php
// Enqueue script with nonce
wp_localize_script('my-script', 'myAjax', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('my_ajax_nonce'),
));

// JS side
/*
jQuery.post(myAjax.ajax_url, {
    action: 'delete_item',
    item_id: 123,
    nonce: myAjax.nonce
}, function(response) { ... });
*/

// PHP handler
function handle_ajax_delete() {
    check_ajax_referer('my_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }

    $item_id = absint($_POST['item_id'] ?? 0);
    $result  = delete_item($item_id);

    $result ? wp_send_json_success() : wp_send_json_error();
    wp_die();
}
add_action('wp_ajax_delete_item', 'handle_ajax_delete');
```

---

## Access Control {#access-control}

### Capability reference

| Capability | Who has it | Use for |
|---|---|---|
| `manage_options` | Admin | Site settings |
| `edit_post` / `delete_post` | depends on post | Object-level operations |
| `edit_posts` / `delete_posts` | Editor, Author | General post operations |
| `publish_posts` | Editor, Author | Publishing |
| `upload_files` | Admin, Editor, Author | Media uploads |
| `moderate_comments` | Admin, Editor | Comments |
| `edit_users` / `create_users` / `delete_users` | Admin | User management |

### REST endpoint

```php
register_rest_route('myplugin/v1', '/posts/(?P<id>\d+)', array(
    'methods'             => 'DELETE',
    'callback'            => 'handle_rest_delete',
    'permission_callback' => function($request) {
        return current_user_can('delete_post', (int) $request['id']);
    },
));

// NEVER do this for authenticated actions:
// 'permission_callback' => '__return_true'
```

---

## File Upload {#file-upload}

### Complete secure handler

```php
function handle_safe_file_upload() {
    // 1. Permission
    if (!current_user_can('upload_files')) {
        wp_die(__('Permission denied', 'textdomain'));
    }

    // 2. Nonce
    check_admin_referer('file_upload_nonce');

    $file = $_FILES['uploaded_file'] ?? null;
    if (!$file) {
        return new WP_Error('no_file', __('No file uploaded', 'textdomain'));
    }

    // 3. PHP error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('upload_error', __('Upload error', 'textdomain'));
    }

    // 4. Size (2 MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        return new WP_Error('too_large', __('File exceeds 2MB', 'textdomain'));
    }

    // 5. MIME + extension whitelist
    $allowed_mimes = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'pdf'          => 'application/pdf',
    );

    // 6. Image content verification
    if (strpos($file['type'], 'image/') === 0) {
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return new WP_Error('not_image', __('Invalid image', 'textdomain'));
        }
        if (!in_array($image_info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF), true)) {
            return new WP_Error('invalid_image_type', __('Image type not allowed', 'textdomain'));
        }

        // Re-render image to strip embedded payloads
        switch ($image_info[2]) {
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg($file['tmp_name']);
                imagejpeg($img, $file['tmp_name'], 90);
                break;
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng($file['tmp_name']);
                imagepng($img, $file['tmp_name']);
                break;
            case IMAGETYPE_GIF:
                $img = imagecreatefromgif($file['tmp_name']);
                imagegif($img, $file['tmp_name']);
                break;
        }
        if (isset($img)) {
            imagedestroy($img);
        }
    }

    // 7. Let WordPress handle final storage and renaming
    $upload = wp_handle_upload($file, array(
        'test_form' => false,
        'mimes'     => $allowed_mimes,
    ));

    if (isset($upload['error'])) {
        return new WP_Error('upload_failed', $upload['error']);
    }

    return $upload;
}
```

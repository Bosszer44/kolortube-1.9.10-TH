<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Super File Manager - อัพเกรดจาก WPS_File_Manager
 * สำหรับธีม Kolortube / AV Control Center 42
 *
 * ฟีเจอร์ใหม่:
 * - ดาวน์โหลดทั้งโฟลเดอร์เป็น ZIP ได้เลย (ทีเดียวไฟล์ทั้งหมด)
 * - UI ใหม่ทันสมัย สวยขึ้นมาก
 * - ไอคอนแยกตามประเภทไฟล์
 * - ปุ่ม ZIP ต่อโฟลเดอร์แต่ละอัน
 * - ปุ่มใหญ่ดาวน์โหลดโฟลเดอร์ปัจจุบัน
 *
 * ความเข้ากันได้:
 * - เก็บชื่อ class, hooks, methods, actions ทุกอย่างเหมือนเดิม
 * - ระบบธีม Kolortube ทำงานต่อได้ปกติไม่พัง
 */
final class WPS_File_Manager {
    private static $instance = null;
    private $text_extensions = array( 'php', 'css', 'js', 'json', 'txt', 'md', 'html', 'htm', 'xml', 'svg', 'yml', 'yaml', 'ini', 'conf', 'log', 'po', 'pot' );

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if ( defined( 'WPMB_MAINTENANCE_OWNER' ) ) { return; }
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
        add_action( 'admin_init', array( $this, 'handle' ) );
        add_action( 'admin_post_wps_42_download', array( $this, 'download' ) );
        add_action( 'admin_post_wps_42_download_zip', array( $this, 'download_zip' ) ); // ใหม่: ดาวน์โหลด ZIP
        add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 100 );
    }

    public function admin_menu() {
        add_submenu_page( 'av-control-center', 'Super File Manager', 'Super File Manager', 'manage_options', 'wps-file-manager', array( $this, 'page' ) );
        add_submenu_page( 'themes.php', 'Super File Manager', 'Super File Manager', 'manage_options', 'wps-file-manager', array( $this, 'page' ) );
    }

    public function admin_bar_menu( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
            return;
        }
        $wp_admin_bar->add_node( array(
            'id'    => 'wps-file-manager-shortcut',
            'title' => 'Super File Manager',
            'href'  => admin_url( 'admin.php?page=wps-file-manager' ),
            'meta'  => array( 'title' => 'เปิด Super File Manager' ),
        ) );
    }

    private function roots() {
        $uploads = wp_upload_dir();
        $roots = array(
            'theme'   => array( 'label' => 'ธีมหลัก', 'path' => get_template_directory() ),
            'uploads' => array( 'label' => 'Uploads', 'path' => $uploads['basedir'] ),
            'plugins' => array( 'label' => 'Plugins', 'path' => WP_PLUGIN_DIR ),
        );
        if ( get_stylesheet_directory() !== get_template_directory() ) {
            $roots['child'] = array( 'label' => 'Child Theme', 'path' => get_stylesheet_directory() );
        }
        return $roots;
    }

    private function selected_root() {
        $roots = $this->roots();
        $key = isset( $_REQUEST['root'] ) ? sanitize_key( wp_unslash( $_REQUEST['root'] ) ) : 'theme';
        return isset( $roots[ $key ] ) ? $key : 'theme';
    }

    private function normalize_relative( $relative ) {
        $relative = str_replace( '\\', '/', (string) $relative );
        $relative = trim( $relative, '/' );
        $parts = array();
        foreach ( explode( '/', $relative ) as $part ) {
            if ( '' === $part || '.' === $part ) {
                continue;
            }
            if ( '..' === $part || false !== strpos( $part, "\0" ) ) {
                return new WP_Error( 'invalid_path', 'เส้นทางไม่ถูกต้อง' );
            }
            $parts[] = $part;
        }
        return implode( '/', $parts );
    }

    private function resolve( $root_key, $relative, $must_exist = true ) {
        $roots = $this->roots();
        if ( ! isset( $roots[ $root_key ] ) ) {
            return new WP_Error( 'invalid_root', 'Root ไม่ถูกต้อง' );
        }
        $relative = $this->normalize_relative( $relative );
        if ( is_wp_error( $relative ) ) {
            return $relative;
        }
        $base = realpath( $roots[ $root_key ]['path'] );
        if ( ! $base ) {
            return new WP_Error( 'missing_root', 'ไม่พบ Root' );
        }
        $candidate = $base . ( $relative ? DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative ) : '' );
        $check = $must_exist ? realpath( $candidate ) : realpath( dirname( $candidate ) );
        if ( ! $check ) {
            return new WP_Error( 'missing_path', 'ไม่พบเส้นทาง' );
        }
        $base_norm = wp_normalize_path( $base );
        $check_norm = wp_normalize_path( $check );
        if ( 0 !== strpos( trailingslashit( $check_norm ), trailingslashit( $base_norm ) ) && $check_norm !== $base_norm ) {
            return new WP_Error( 'outside_root', 'เส้นทางอยู่นอก Root ที่อนุญาต' );
        }
        return $must_exist ? $check : $candidate;
    }

    private function backup( $path, $root_key, $relative ) {
        if ( ! is_file( $path ) ) {
            return true;
        }
        $parent_base = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . '.wps-private-backups';
        $fallback_base = trailingslashit( WP_CONTENT_DIR ) . '.wps-private-backups';
        $backup_base = wp_mkdir_p( $parent_base ) && is_writable( $parent_base ) ? $parent_base : $fallback_base;
        if ( ! wp_mkdir_p( $backup_base ) ) {
            return new WP_Error( 'backup_dir', 'สร้างพื้นที่สำรองส่วนตัวไม่ได้' );
        }
        $dir = trailingslashit( $backup_base ) . 'files/' . gmdate( 'Ymd-His' ) . '/' . sanitize_key( $root_key );
        $target = trailingslashit( $dir ) . ltrim( str_replace( '\\', '/', $relative ), '/' ) . '.bak';
        if ( ! wp_mkdir_p( dirname( $target ) ) ) {
            return new WP_Error( 'backup_dir', 'สร้างโฟลเดอร์สำรองไม่ได้' );
        }
        $protect = trailingslashit( $backup_base ) . '.htaccess';
        if ( ! file_exists( $protect ) ) {
            @file_put_contents( $protect, "Require all denied\nDeny from all\n" );
            @file_put_contents( trailingslashit( $backup_base ) . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>" );
            @file_put_contents( trailingslashit( $backup_base ) . 'index.php', "<?php\nhttp_response_code(404);\nexit;\n" );
        }
        return @copy( $path, $target ) ? true : new WP_Error( 'backup_failed', 'สำรองไฟล์ไม่ได้ จึงยกเลิกการแก้ไข' );
    }

    public function handle() {
		if (
			'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' )
			&& isset( $_GET['page'] )
			&& 'wps-file-manager' === sanitize_key( wp_unslash( $_GET['page'] ) )
			&& empty( $_POST )
			&& ! empty( $_SERVER['CONTENT_LENGTH'] )
		) {
			$root = $this->selected_root();
			$this->redirect_error( $root, '', sprintf( 'The upload was rejected before WordPress received it. Increase post_max_size and upload_max_filesize (current WordPress limit: %s).', size_format( wp_max_upload_size() ) ) );
		}
        if ( empty( $_POST['avsora_file_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( 'avsora_file_action' );
        $action = sanitize_key( wp_unslash( $_POST['avsora_file_action'] ) );
        $root = $this->selected_root();
        $relative = isset( $_POST['path'] ) ? wp_unslash( $_POST['path'] ) : '';
        $message = 'done';
        if ( 'save' === $action ) {
            $path = $this->resolve( $root, $relative, true );
            if ( is_wp_error( $path ) || ! is_file( $path ) || ! $this->is_editable( $path ) ) {
                $this->redirect_error( $root, $relative, is_wp_error( $path ) ? $path->get_error_message() : 'ไฟล์นี้ไม่อนุญาตให้แก้ไข' );
            }
            $backup = $this->backup( $path, $root, $relative );
            if ( is_wp_error( $backup ) ) {
                $this->redirect_error( $root, $relative, $backup->get_error_message() );
            }
            $content = isset( $_POST['file_content'] ) ? wp_unslash( $_POST['file_content'] ) : '';
            $tmp = $path . '.avsora-tmp-' . wp_generate_password( 8, false, false );
            if ( false === @file_put_contents( $tmp, $content, LOCK_EX ) || ! @rename( $tmp, $path ) ) {
                @unlink( $tmp );
                $this->redirect_error( $root, $relative, 'เขียนไฟล์ไม่สำเร็จ' );
            }
            $message = 'saved';
        } elseif ( 'create_file' === $action || 'create_dir' === $action ) {
            $current = $this->resolve( $root, $relative, true );
            if ( is_wp_error( $current ) || ! is_dir( $current ) ) {
                $this->redirect_error( $root, $relative, 'โฟลเดอร์ปัจจุบันไม่ถูกต้อง' );
            }
            $name = sanitize_file_name( wp_unslash( $_POST['new_name'] ?? '' ) );
            if ( ! $name ) {
                $this->redirect_error( $root, $relative, 'กรุณาใส่ชื่อ' );
            }
            $target = $current . DIRECTORY_SEPARATOR . $name;
            if ( file_exists( $target ) ) {
                $this->redirect_error( $root, $relative, 'มีชื่อไฟล์หรือโฟลเดอร์นี้แล้ว' );
            }
            $ok = 'create_dir' === $action ? wp_mkdir_p( $target ) : false !== @file_put_contents( $target, '' );
            if ( ! $ok ) {
                $this->redirect_error( $root, $relative, 'สร้างไม่สำเร็จ' );
            }
            $message = 'created';
        } elseif ( 'rename' === $action ) {
            $path = $this->resolve( $root, $relative, true );
            if ( is_wp_error( $path ) ) {
                $this->redirect_error( $root, dirname( $relative ), $path->get_error_message() );
            }
            $name = sanitize_file_name( wp_unslash( $_POST['new_name'] ?? '' ) );
            if ( ! $name ) {
                $this->redirect_error( $root, dirname( $relative ), 'ชื่อใหม่ไม่ถูกต้อง' );
            }
            if ( is_file( $path ) ) {
                $backup = $this->backup( $path, $root, $relative );
                if ( is_wp_error( $backup ) ) {
                    $this->redirect_error( $root, dirname( $relative ), $backup->get_error_message() );
                }
            }
            $target = dirname( $path ) . DIRECTORY_SEPARATOR . $name;
            if ( file_exists( $target ) || ! @rename( $path, $target ) ) {
                $this->redirect_error( $root, dirname( $relative ), 'เปลี่ยนชื่อไม่สำเร็จ' );
            }
            $relative = trim( dirname( $relative ), './\\' );
            $message = 'renamed';
        } elseif ( 'delete' === $action ) {
            $path = $this->resolve( $root, $relative, true );
            if ( is_wp_error( $path ) || wp_normalize_path( $path ) === wp_normalize_path( $this->roots()[ $root ]['path'] ) ) {
                $this->redirect_error( $root, '', 'ห้ามลบ Root' );
            }
            if ( is_dir( $path ) ) {
                $items = array_diff( scandir( $path ), array( '.', '..' ) );
                if ( $items || ! @rmdir( $path ) ) {
                    $this->redirect_error( $root, dirname( $relative ), 'ลบได้เฉพาะโฟลเดอร์ว่าง' );
                }
            } else {
                $backup = $this->backup( $path, $root, $relative );
                if ( is_wp_error( $backup ) || ! @unlink( $path ) ) {
                    $this->redirect_error( $root, dirname( $relative ), is_wp_error( $backup ) ? $backup->get_error_message() : 'ลบไฟล์ไม่สำเร็จ' );
                }
            }
            $relative = trim( dirname( $relative ), './\\' );
            $message = 'deleted';
        } elseif ( 'upload' === $action ) {
            $dir = $this->resolve( $root, $relative, true );
            if ( is_wp_error( $dir ) || ! is_dir( $dir ) ) {
				$this->redirect_error( $root, $relative, is_wp_error( $dir ) ? $dir->get_error_message() : 'The upload directory is invalid.' );
            }
			if ( ! wp_is_writable( $dir ) ) {
				$this->redirect_error( $root, $relative, 'The destination directory is not writable.' );
			}
			$file = isset( $_FILES['upload_file'] ) && is_array( $_FILES['upload_file'] ) ? $_FILES['upload_file'] : array();
			$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
			if ( UPLOAD_ERR_OK !== $error ) {
				$this->redirect_error( $root, $relative, $this->upload_error_message( $error ) );
			}
			if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
				$this->redirect_error( $root, $relative, 'No upload file was received.' );
			}
			$name = sanitize_file_name( wp_unslash( $file['name'] ) );
			if ( '' === $name ) {
				$this->redirect_error( $root, $relative, 'The uploaded filename is invalid.' );
			}
			$max_size = wp_max_upload_size();
			if ( ! empty( $file['size'] ) && $max_size > 0 && (int) $file['size'] > $max_size ) {
				$this->redirect_error( $root, $relative, sprintf( 'The file is too large. Maximum upload size: %s.', size_format( $max_size ) ) );
			}
            $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
            $allowed = array_merge( $this->text_extensions, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'zip', 'rar', '7z', 'tar', 'gz', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'mp3', 'mp4', 'wav', 'avi', 'mov' ) );
            if ( ! in_array( $extension, $allowed, true ) ) {
                $this->redirect_error( $root, $relative, 'นามสกุลไฟล์ไม่อยู่ในรายการที่อนุญาต' );
            }
            if ( in_array( $extension, array( 'php', 'js' ), true ) && empty( $_POST['confirm_code_upload'] ) ) {
                $this->redirect_error( $root, $relative, 'ไฟล์โค้ดต้องติ๊กยืนยันก่อนอัปโหลด' );
            }
            $target = $dir . DIRECTORY_SEPARATOR . wp_unique_filename( $dir, $name );
            if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
				$this->redirect_error( $root, $relative, 'The temporary file is not a valid HTTP upload.' );
			}
			if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
				$this->redirect_error( $root, $relative, 'The server could not move the uploaded file into the destination directory.' );
            }
            @chmod( $target, 0644 );
            $message = 'uploaded';
        }
        wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-file-manager', array( 'root' => $root, 'path' => $relative, 'fm_notice' => $message ) ) );
        exit;
    }

    private function redirect_error( $root, $path, $message ) {
        wp_safe_redirect( WPS_Control_Center_42::admin_page_url( 'wps-file-manager', array( 'root' => $root, 'path' => $path, 'fm_error' => rawurlencode( $message ) ) ) );
        exit;
    }

	private function upload_error_message( $error ) {
		$messages = array(
			UPLOAD_ERR_INI_SIZE   => sprintf( 'The file exceeds the server upload_max_filesize limit (WordPress limit: %s).', size_format( wp_max_upload_size() ) ),
			UPLOAD_ERR_FORM_SIZE  => sprintf( 'The file exceeds the form upload limit (%s).', size_format( wp_max_upload_size() ) ),
			UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
			UPLOAD_ERR_NO_FILE    => 'No file was selected.',
			UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload directory is missing.',
			UPLOAD_ERR_CANT_WRITE => 'The server could not write the temporary upload file to disk.',
			UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
		);
		return isset( $messages[ $error ] ) ? $messages[ $error ] : sprintf( 'Upload failed with PHP error code %d.', (int) $error );
	}

    private function is_editable( $path ) {
        return in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), $this->text_extensions, true ) && filesize( $path ) <= 2 * MB_IN_BYTES;
    }

    /**
     * ฟีเจอร์เดิม: ดาวน์โหลดไฟล์เดี่ยว
     */
    public function download() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( 'avsora_cc_download' );
        $root = $this->selected_root();
        $path = $this->resolve( $root, wp_unslash( $_GET['path'] ?? '' ), true );
        if ( is_wp_error( $path ) || ! is_file( $path ) ) {
            wp_die( 'File not found' );
        }
        nocache_headers();
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . rawurlencode( basename( $path ) ) . '"' );
        header( 'Content-Length: ' . filesize( $path ) );
        readfile( $path );
        exit;
    }

    /**
     * ฟีเจอร์ใหม่: ดาวน์โหลดทั้งโฟลเดอร์เป็น ZIP
     */
    public function download_zip() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( 'avsora_cc_download_zip' );

        $root = $this->selected_root();
        $relative = wp_unslash( $_GET['path'] ?? '' );
        $dir_path = $this->resolve( $root, $relative, true );

        if ( is_wp_error( $dir_path ) || ! is_dir( $dir_path ) ) {
            wp_die( 'ไม่พบโฟลเดอร์หรือไม่สามารถเข้าถึงได้' );
        }

        // สร้างไฟล์ ZIP ชั่วคราว
        $zip_name = ( $relative ? basename( $relative ) : $root ) . '-' . date( 'Ymd-His' ) . '.zip';
        $temp_dir = get_temp_dir();
        $zip_path = $temp_dir . 'sfm_' . uniqid() . '.zip';

        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
                wp_die( 'ไม่สามารถสร้างไฟล์ ZIP ได้' );
            }

            // เพิ่มไฟล์ทั้งหมดลง ZIP
            $this->add_folder_to_zip( $dir_path, $zip, basename( $dir_path ) );
            $zip->close();
        } else {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
            $archive = new PclZip( $zip_path );
            $result  = $archive->create( $dir_path, PCLZIP_OPT_REMOVE_PATH, dirname( $dir_path ) );
            if ( 0 === $result ) {
                wp_die( 'ไม่สามารถสร้างไฟล์ ZIP ได้: ' . esc_html( $archive->errorInfo( true ) ) );
            }
        }

        if ( ! file_exists( $zip_path ) ) {
            wp_die( 'ไม่สามารถสร้างไฟล์ ZIP ได้' );
        }

        // ดาวน์โหลด
        nocache_headers();
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $zip_name . '"' );
        header( 'Content-Length: ' . filesize( $zip_path ) );
        header( 'Content-Transfer-Encoding: binary' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Expires: 0' );

        if ( ob_get_level() ) {
            ob_end_clean();
        }

        readfile( $zip_path );
        @unlink( $zip_path ); // ลบไฟล์ชั่วคราวทันที
        exit;
    }

    /**
     * Helper: เพิ่มโฟลเดอร์และไฟล์ย่อยลง ZIP แบบเรียกซ้ำ
     */
    private function add_folder_to_zip( $folder, $zip, $zip_folder = '' ) {
        $handle = opendir( $folder );
        if ( ! $handle ) return;

        while ( false !== ( $entry = readdir( $handle ) ) ) {
            if ( $entry === '.' || $entry === '..' ) continue;

            $path = $folder . DIRECTORY_SEPARATOR . $entry;
            $zip_path = $zip_folder ? $zip_folder . '/' . $entry : $entry;

            if ( is_dir( $path ) ) {
                $zip->addEmptyDir( $zip_path );
                $this->add_folder_to_zip( $path, $zip, $zip_path );
            } elseif ( is_file( $path ) && is_readable( $path ) ) {
                $zip->addFile( $path, $zip_path );
            }
        }

        closedir( $handle );
    }

    /**
     * Helper: ได้ไอคอนตามประเภทไฟล์
     */
    private function get_file_icon( $ext ) {
        $ext = strtolower( $ext );
        $icons = array(
            'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'webp' => '🖼️', 'avif' => '🖼️', 'svg' => '🖼️',
            'pdf' => '📕',
            'doc' => '📄', 'docx' => '📄',
            'xls' => '📊', 'xlsx' => '📊', 'csv' => '📊',
            'ppt' => '📽️', 'pptx' => '📽️',
            'zip' => '📦', 'rar' => '📦', '7z' => '📦', 'tar' => '📦', 'gz' => '📦',
            'php' => '🐘', 'js' => '📜', 'css' => '🎨', 'html' => '🌐', 'htm' => '🌐',
            'sql' => '🗄️', 'json' => '🔧', 'xml' => '🔧', 'yml' => '🔧', 'yaml' => '🔧',
            'txt' => '📝', 'log' => '📝', 'md' => '📝', 'ini' => '⚙️', 'conf' => '⚙️',
            'mp3' => '🎵', 'wav' => '🎵',
            'mp4' => '🎬', 'avi' => '🎬', 'mov' => '🎬',
            'po' => '🌍', 'pot' => '🌍',
        );
        return isset( $icons[ $ext ] ) ? $icons[ $ext ] : '📄';
    }

    /**
     * หน้าแสดงผลหลัก — UI ใหม่ทั้งหมด
     */
    public function page() {
        $root = $this->selected_root();
        $relative = isset( $_GET['path'] ) ? wp_unslash( $_GET['path'] ) : '';
        $resolved = $this->resolve( $root, $relative, true );

        if ( is_wp_error( $resolved ) ) {
            $relative = '';
            $resolved = $this->resolve( $root, '', true );
        }

        $roots = $this->roots();
        $editing = is_file( $resolved );
        $dir = $editing ? dirname( $resolved ) : $resolved;

        // URL สำหรับดาวน์โหลด ZIP โฟลเดอร์ปัจจุบัน
        $zip_download_url = wp_nonce_url(
            add_query_arg(
                array( 'action' => 'wps_42_download_zip', 'root' => $root, 'path' => $editing ? trim( dirname( $relative ), './\\' ) : $relative ),
                admin_url( 'admin-post.php' )
            ),
            'avsora_cc_download_zip'
        );
        ?>
        <style>
        .sfm-wrap {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 20px 20px 0 0;
        }
        .sfm-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 28px;
            color: #fff;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
        }
        .sfm-header h1 {
            color: #fff;
            margin: 0 0 6px 0;
            font-size: 26px;
            font-weight: 700;
        }
        .sfm-header p {
            color: rgba(255,255,255,0.9);
            margin: 0;
            font-size: 13px;
        }
        .sfm-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .sfm-card h2 {
            margin: 0 0 14px 0;
            font-size: 16px;
            color: #1a1a2e;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sfm-warning {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 16px;
            color: #92400e;
            font-size: 13px;
        }
        .sfm-toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .sfm-toolbar select, .sfm-toolbar input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 13px;
        }
        .sfm-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .sfm-btn:hover {
            transform: translateY(-1px);
        }
        .sfm-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .sfm-btn-primary:hover {
            opacity: 0.9;
            color: #fff;
        }
        .sfm-btn-zip {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #fff;
        }
        .sfm-btn-zip:hover {
            opacity: 0.9;
            color: #fff;
        }
        .sfm-btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
        }
        .sfm-btn-success:hover {
            opacity: 0.9;
            color: #fff;
        }
        .sfm-btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }
        .sfm-btn-danger:hover {
            background: #fecaca;
            color: #dc2626;
        }
        .sfm-btn-small {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 6px;
        }
        .sfm-file-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sfm-file-table th {
            text-align: left;
            padding: 12px 14px;
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            font-size: 13px;
        }
        .sfm-file-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
            vertical-align: middle;
        }
        .sfm-file-table tr:hover {
            background: #fafafa;
        }
        .sfm-file-name {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            color: #1f2937;
            text-decoration: none;
        }
        .sfm-file-name:hover {
            color: #667eea;
        }
        .sfm-file-icon {
            font-size: 20px;
        }
        .sfm-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 782px) {
            .sfm-grid {
                grid-template-columns: 1fr;
            }
        }
        .sfm-form-inline {
            display: inline-flex;
            gap: 4px;
            align-items: center;
            margin-right: 4px;
        }
        .sfm-form-inline input[type="text"] {
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 12px;
            width: 120px;
        }
        .sfm-code-editor {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.5;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fafafa;
        }
        .sfm-path-display {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 14px;
            font-family: monospace;
            font-size: 13px;
            color: #0369a1;
        }
        .sfm-stats {
            display: flex;
            gap: 20px;
            padding: 10px 14px;
            background: #f0fdf4;
            border-radius: 8px;
            margin-bottom: 14px;
            font-size: 13px;
            color: #166534;
        }
        .sfm-empty {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
        }
        .sfm-empty-icon {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        </style>

        <div class="sfm-wrap">
            <div class="sfm-header">
                <h1>📦 Super File Manager</h1>
                <p>จัดการไฟล์ธีม Kolortube | ดาวน์โหลดทั้งโฟลเดอร์เป็น ZIP ได้เลย | ทุกการแก้ไขมี Backup อัตโนมัติ</p>
            </div>

            <?php if ( ! empty( $_GET['fm_error'] ) ) : ?>
                <div class="notice notice-error" style="border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                    <strong>❌ ผิดพลาด:</strong> <?php echo esc_html( rawurldecode( wp_unslash( $_GET['fm_error'] ) ) ); ?>
                </div>
            <?php endif; ?>
            <?php if ( ! empty( $_GET['fm_notice'] ) ) : ?>
                <div class="notice notice-success is-dismissible" style="border-radius:8px;padding:12px 16px;margin-bottom:16px;border-left:4px solid #10b981;">
                    <strong>✅ ดำเนินการเรียบร้อย:</strong> <?php echo esc_html( sanitize_key( $_GET['fm_notice'] ) ); ?>
                </div>
            <?php endif; ?>

            <div class="sfm-warning">
                <strong>🔒 ความปลอดภัย:</strong> ทุกครั้งที่แก้ไข เปลี่ยนชื่อ หรือลบไฟล์ ระบบจะสำรองไฟล์เดิมอัตโนมัติ | ห้ามใช้หน้านี้แทนการสำรองเว็บไซต์เต็มรูปแบบ
            </div>

            <!-- Toolbar: เลือก Root -->
            <div class="sfm-card">
                <form method="get" class="sfm-toolbar">
                    <input type="hidden" name="page" value="wps-file-manager" />
                    <strong style="color:#374151;">📍 Root:</strong>
                    <select name="root" onchange="this.form.submit()">
                        <?php foreach ( $roots as $key => $item ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $root, $key ); ?>>
                                <?php echo esc_html( $item['label'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="path" value="<?php echo esc_attr( $relative ); ?>" placeholder="พาธย่อย เช่น inc/backup" style="min-width:200px;" />
                    <button type="submit" class="sfm-btn sfm-btn-primary">เปิด</button>
                    <?php if ( ! $editing ) : ?>
                        <a href="<?php echo esc_url( $zip_download_url ); ?>" class="sfm-btn sfm-btn-zip" style="margin-left:auto;" title="ดาวน์โหลดทุกไฟล์ในโฟลเดอร์นี้รวมถึงโฟลเดอร์ย่อย">
                            📦 ดาวน์โหลดโฟลเดอร์นี้เป็น ZIP
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ( $editing ) :
                $content = $this->is_editable( $resolved ) ? file_get_contents( $resolved ) : '';
                $download_url = wp_nonce_url(
                    add_query_arg( array( 'action' => 'wps_42_download', 'root' => $root, 'path' => $relative ), admin_url( 'admin-post.php' ) ),
                    'avsora_cc_download'
                );
                $back_url = WPS_Control_Center_42::admin_page_url( 'wps-file-manager', array( 'root' => $root, 'path' => trim( dirname( $relative ), './\\' ) ) );
            ?>
                <!-- โหมดแก้ไขไฟล์ -->
                <div class="sfm-card">
                    <h2>✏️ แก้ไขไฟล์: <?php echo esc_html( $relative ); ?></h2>
                    <div class="sfm-path-display">
                        📍 <?php echo esc_html( $resolved ); ?>
                        <span style="margin-left:10px;color:#64748b;">| ขนาด: <?php echo esc_html( size_format( filesize( $resolved ) ) ); ?></span>
                        <span style="margin-left:10px;color:#64748b;">| แก้ไขล่าสุด: <?php echo esc_html( date( 'Y-m-d H:i:s', filemtime( $resolved ) ) ); ?></span>
                    </div>

                    <?php if ( $this->is_editable( $resolved ) ) : ?>
                        <form method="post">
                            <?php wp_nonce_field( 'avsora_file_action' ); ?>
                            <input type="hidden" name="avsora_file_action" value="save" />
                            <input type="hidden" name="root" value="<?php echo esc_attr( $root ); ?>" />
                            <input type="hidden" name="path" value="<?php echo esc_attr( $relative ); ?>" />
                            <textarea name="file_content" class="sfm-code-editor" rows="25" style="width:100%;"><?php echo esc_textarea( $content ); ?></textarea>
                            <p style="margin-top:14px;">
                                <button type="submit" class="sfm-btn sfm-btn-primary">💾 สำรองและบันทึกไฟล์</button>
                                <a href="<?php echo esc_url( $download_url ); ?>" class="sfm-btn sfm-btn-success">⬇️ ดาวน์โหลด</a>
                                <a href="<?php echo esc_url( $back_url ); ?>" class="sfm-btn" style="background:#f3f4f6;color:#374151;">↩️ กลับโฟลเดอร์</a>
                            </p>
                        </form>
                    <?php else : ?>
                        <p style="color:#64748b;">ไฟล์นี้เปิดดาวน์โหลดได้ แต่ไม่อนุญาตให้แก้ไขในเว็บ (รองรับไฟล์ข้อความไม่เกิน 2 MB)</p>
                        <p>
                            <a href="<?php echo esc_url( $download_url ); ?>" class="sfm-btn sfm-btn-success">⬇️ ดาวน์โหลด</a>
                            <a href="<?php echo esc_url( $back_url ); ?>" class="sfm-btn" style="background:#f3f4f6;color:#374151;">↩️ กลับโฟลเดอร์</a>
                        </p>
                    <?php endif; ?>
                </div>

            <?php else : ?>
                <!-- โหมดแสดงรายการไฟล์ -->
                <div class="sfm-card">
                    <h2>📂 <?php echo esc_html( $roots[ $root ]['label'] . ( $relative ? '/' . $relative : '' ) ); ?></h2>
                    <div class="sfm-path-display">📍 <?php echo esc_html( $dir ); ?></div>

                    <?php
                    $items = scandir( $dir );
                    $file_count = 0;
                    $dir_count = 0;
                    $total_size = 0;

                    // นับสถิติก่อน
                    foreach ( $items as $item ) {
                        if ( '.' === $item || '..' === $item ) continue;
                        $item_path = $dir . DIRECTORY_SEPARATOR . $item;
                        if ( is_dir( $item_path ) ) {
                            $dir_count++;
                        } else {
                            $file_count++;
                            $total_size += filesize( $item_path );
                        }
                    }
                    ?>
                    <div class="sfm-stats">
                        <span>📁 โฟลเดอร์: <strong><?php echo esc_html( $dir_count ); ?></strong></span>
                        <span>📄 ไฟล์: <strong><?php echo esc_html( $file_count ); ?></strong></span>
                        <span>📦 ขนาดรวม: <strong><?php echo esc_html( size_format( $total_size ) ); ?></strong></span>
                    </div>

                    <table class="sfm-file-table">
                        <thead>
                            <tr>
                                <th style="width:40%;">ชื่อ</th>
                                <th style="width:12%;">ชนิด</th>
                                <th style="width:12%;">ขนาด</th>
                                <th style="width:12%;">แก้ไขล่าสุด</th>
                                <th style="width:24%;">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $has_items = false;
                        foreach ( $items as $item ) {
                            if ( '.' === $item || ( '..' === $item && '' === trim( $relative ) ) ) { continue; }
                            $has_items = true;
                            $item_rel = '..' === $item ? trim( dirname( $relative ), './\\' ) : trim( $relative . '/' . $item, '/' );
                            $item_path = '..' === $item ? dirname( $dir ) : $dir . DIRECTORY_SEPARATOR . $item;
                            $is_dir = is_dir( $item_path );
                            $url = WPS_Control_Center_42::admin_page_url( 'wps-file-manager', array( 'root' => $root, 'path' => $item_rel ) );
                            $ext = strtolower( pathinfo( $item, PATHINFO_EXTENSION ) );
                            $icon = $is_dir ? ( '..' === $item ? '⬆️' : '📁' ) : $this->get_file_icon( $ext );
                            $type_label = $is_dir ? ( '..' === $item ? '.. (ย้อนกลับ)' : 'โฟลเดอร์' ) : strtoupper( $ext );

                            echo '<tr>';
                            echo '<td><a href="' . esc_url( $url ) . '" class="sfm-file-name"><span class="sfm-file-icon">' . $icon . '</span>' . esc_html( $item ) . '</a></td>';
                            echo '<td>' . esc_html( $type_label ) . '</td>';
                            echo '<td>' . esc_html( $is_dir ? '—' : size_format( filesize( $item_path ) ) ) . '</td>';
                            echo '<td>' . esc_html( $is_dir ? '—' : date( 'Y-m-d H:i', filemtime( $item_path ) ) ) . '</td>';
                            echo '<td>';

                            if ( '..' !== $item ) {
                                if ( $is_dir ) {
                                    // โฟลเดอร์: เปิด + ดาวน์โหลด ZIP + รีเนม + ลบ
                                    $item_zip_url = wp_nonce_url(
                                        add_query_arg( array( 'action' => 'wps_42_download_zip', 'root' => $root, 'path' => $item_rel ), admin_url( 'admin-post.php' ) ),
                                        'avsora_cc_download_zip'
                                    );
                                    echo '<a href="' . esc_url( $url ) . '" class="sfm-btn sfm-btn-small sfm-btn-primary">📁 เปิด</a>';
                                    echo '<a href="' . esc_url( $item_zip_url ) . '" class="sfm-btn sfm-btn-small sfm-btn-zip" title="ดาวน์โหลดโฟลเดอร์นี้เป็น ZIP">📦 ZIP</a>';
                                } else {
                                    // ไฟล์: เปิดแก้ไข/ดู + ดาวน์โหลด
                                    $dl_url = wp_nonce_url(
                                        add_query_arg( array( 'action' => 'wps_42_download', 'root' => $root, 'path' => $item_rel ), admin_url( 'admin-post.php' ) ),
                                        'avsora_cc_download'
                                    );
                                    echo '<a href="' . esc_url( $url ) . '" class="sfm-btn sfm-btn-small sfm-btn-primary">✏️ เปิด</a>';
                                    echo '<a href="' . esc_url( $dl_url ) . '" class="sfm-btn sfm-btn-small sfm-btn-success">⬇️ ดาวน์โหลด</a>';
                                }

                                // ฟอร์มรีเนมและลบ (สำหรับทั้งไฟล์และโฟลเดอร์)
                                echo '<form method="post" class="sfm-form-inline" style="margin-left:4px;">';
                                wp_nonce_field( 'avsora_file_action' );
                                echo '<input type="hidden" name="root" value="' . esc_attr( $root ) . '">';
                                echo '<input type="hidden" name="path" value="' . esc_attr( $item_rel ) . '">';
                                echo '<input type="hidden" name="avsora_file_action" value="rename">';
                                echo '<input type="text" name="new_name" value="' . esc_attr( $item ) . '" title="เปลี่ยนชื่อ">';
                                echo '<button type="submit" class="sfm-btn sfm-btn-small" style="background:#f3f4f6;color:#374151;">✏️</button>';
                                echo '</form>';

                                echo '<form method="post" class="sfm-form-inline" onsubmit="return confirm(\'ยืนยันลบรายการนี้? ระบบจะสำรองไฟล์ก่อนลบ\');">';
                                wp_nonce_field( 'avsora_file_action' );
                                echo '<input type="hidden" name="root" value="' . esc_attr( $root ) . '">';
                                echo '<input type="hidden" name="path" value="' . esc_attr( $item_rel ) . '">';
                                echo '<input type="hidden" name="avsora_file_action" value="delete">';
                                echo '<button type="submit" class="sfm-btn sfm-btn-small sfm-btn-danger">🗑️</button>';
                                echo '</form>';
                            } else {
                                echo '<a href="' . esc_url( $url ) . '" class="sfm-btn sfm-btn-small" style="background:#f3f4f6;color:#374151;">⬆️ กลับ</a>';
                            }

                            echo '</td></tr>';
                        }

                        if ( ! $has_items ) {
                            echo '<tr><td colspan="5"><div class="sfm-empty"><div class="sfm-empty-icon">📂</div><p>โฟลเดอร์ว่างเปล่า</p></div></td></tr>';
                        }
                        ?>
                        </tbody>
                    </table>
                </div>

                <!-- สร้างใหม่ + อัพโหลด -->
                <div class="sfm-grid">
                    <div class="sfm-card">
                        <h2>➕ สร้างใหม่</h2>
                        <form method="post">
                            <?php wp_nonce_field( 'avsora_file_action' ); ?>
                            <input type="hidden" name="root" value="<?php echo esc_attr( $root ); ?>">
                            <input type="hidden" name="path" value="<?php echo esc_attr( $relative ); ?>">
                            <p>
                                <input type="text" name="new_name" required placeholder="ชื่อไฟล์หรือโฟลเดอร์" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                            </p>
                            <p>
                                <button type="submit" name="avsora_file_action" value="create_file" class="sfm-btn sfm-btn-primary">📄 สร้างไฟล์</button>
                                <button type="submit" name="avsora_file_action" value="create_dir" class="sfm-btn" style="background:#f3f4f6;color:#374151;">📁 สร้างโฟลเดอร์</button>
                            </p>
                        </form>
                    </div>

                    <div class="sfm-card">
                        <h2>⬆️ อัปโหลดไฟล์</h2>
                        <form method="post" enctype="multipart/form-data">
                            <?php wp_nonce_field( 'avsora_file_action' ); ?>
                            <input type="hidden" name="root" value="<?php echo esc_attr( $root ); ?>">
                            <input type="hidden" name="path" value="<?php echo esc_attr( $relative ); ?>">
                            <input type="hidden" name="avsora_file_action" value="upload">
                            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( wp_max_upload_size() ); ?>">
                            <p>
                                <input type="file" name="upload_file" required style="width:100%;">
                            </p>
                            <p style="font-size:12px;color:#64748b;margin:0 0 8px 0;">
                                📏 ขนาดสูงสุด: <?php echo esc_html( size_format( wp_max_upload_size() ) ); ?>
                            </p>
                            <p style="margin:0 0 10px 0;">
                                <label style="font-size:13px;color:#374151;cursor:pointer;">
                                    <input type="checkbox" name="confirm_code_upload" value="1">
                                    ยืนยันการอัปโหลดไฟล์ PHP/JS
                                </label>
                            </p>
                            <button type="submit" class="sfm-btn sfm-btn-success">🚀 อัปโหลด</button>
                        </form>
                    </div>
                </div>

            <?php endif; ?>
        </div>
        <?php
    }
}

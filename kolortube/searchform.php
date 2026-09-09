<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wps-search-shell">
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-wps-search autocomplete="off">
	<input type="search" class="search-field" placeholder="<?php echo esc_attr( class_exists( 'WPS_Professional_Suite' ) ? WPS_Professional_Suite::text( 'ค้นหาชื่อเรื่อง รหัส หมวดหมู่ นักแสดง สตูดิโอ...', 'Search title, code, category, actor or studio...' ) : esc_attr__( 'Search...', 'wpst' ) ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" title="<?php echo esc_attr__( 'Search for:', 'wpst' ); ?>" aria-autocomplete="list" aria-controls="wps-search-suggestions" />
	<div id="wps-search-suggestions" class="wps-search-suggestions" data-wps-suggestions role="listbox" aria-live="polite"></div>
</form>
</div>

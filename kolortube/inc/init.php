<?php
/**
 * KolorTube native theme - license/connection gate removed (Option A).
 *
 * This file previously killed the whole site with die() unless WP-Script Core
 * was active and the product status for KOT was connected. No other file
 * requires this file, so it is now an intentional no-op: the site can never
 * be locked behind a license/connection check again.
 *
 * @package kolortube
 */

defined( 'ABSPATH' ) || exit;

// Intentionally empty - the license/connection lock is permanently disabled.
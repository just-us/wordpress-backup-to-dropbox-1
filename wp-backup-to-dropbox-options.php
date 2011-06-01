<?php
/**
 * This file contains the contents of the Dropbox admin options page.
 *
 * @copyright Copyright (C) 2011 Michael De Wildt. All rights reserved.
 * @author Michael De Wildt (http://www.mikeyd.com.au/)
 * @license This program is free software; you can redistribute it and/or modify
 *          it under the terms of the GNU General Public License as published by
 *          the Free Software Foundation; either version 2 of the License, or
 *          (at your option) any later version.
 *
 *          This program is distributed in the hope that it will be useful,
 *          but WITHOUT ANY WARRANTY; without even the implied warranty of
 *          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *          GNU General Public License for more details.
 *
 *          You should have received a copy of the GNU General Public License
 *          along with this program; if not, write to the Free Software
 *          Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110, USA.
 */
try {
    global $wpdb;

    $validation_errors = null;
    $message = null;

    $dropbox = new Dropbox_Facade();
    $backup = new WP_Backup( $dropbox, $wpdb );
    $disable_backup_now = $backup->in_progress();

    //We have a form submit so update the schedule and options
    if ( array_key_exists( 'save_changes', $_POST ) ) {
        check_admin_referer( 'backup_to_dropbox_options_save' );
        $backup->set_schedule( $_POST[ 'day' ], $_POST[ 'time' ], $_POST[ 'frequency' ] );
        $validation_errors = $backup->set_options( $_POST[ 'dump_location' ], $_POST[ 'dropbox_location' ], $_POST[ 'keep_local' ], $_POST[ 'backup_count' ] );
        $message = __( 'Settings Saved.' );
    } else if ( array_key_exists( 'backup_now', $_POST ) ) {
        check_admin_referer( 'backup_to_dropbox_options_save' );
        $backup->backup_now();
        $disable_backup_now = true;
        $message = __( 'Backup scheduled to begin now.' );
    }

    //Lets grab the schedule and the options to display to the user
    list( $unixtime, $frequency ) = $backup->get_schedule();
    if ( !$frequency ) {
        $frequency = 'weekly';
    }
    list( $dump_location, $dropbox_location ) = $backup->get_options();

    if ( !empty( $validation_errors ) ) {
        $dump_location = array_key_exists( 'dump_location', $validation_errors )
                ? $validation_errors[ 'dump_location' ][ 'original' ] : $dump_location;
        $dropbox_location = array_key_exists( 'dropbox_location', $validation_errors )
                ? $validation_errors[ 'dropbox_location' ][ 'original' ] : $dropbox_location;
    }

    $time = date( 'H:i', $unixtime );
    $day = date( 'D', $unixtime );
    ?>
<script type="text/javascript" language="javascript">
    jQuery( document ).ready( function ( $ ) {
        $( '#frequency' ).change( function() {
            var len = $( '#day option' ).size();
            if ( $( '#frequency' ).val() == 'daily' ) {
                $( '#day' ).append( $( "<option></option>" ).attr( "value", "" ).text( '<?php _e( 'Daily' ); ?>' ) );
                $( '#day option:last' ).attr( 'selected', 'selected' );
                $( '#day' ).attr( 'disabled', 'disabled' );
            } else if ( len == 8 ) {
                $( '#day' ).removeAttr( 'disabled' );
                $( '#day option:last' ).remove();
            }
        } );
    } );

    function dropbox_authorize( url ) {
        window.open( url );
        document.getElementById( 'continue' ).style.visibility = 'visible';
        document.getElementById( 'authorize' ).style.visibility = 'hidden';
    }
</script>
<style type="text/css">
    .backup_error {
        margin-left: 10px;
        color: red;
    }

    .backup_ok {
        margin-left: 10px;
        color: green;
    }

    .backup_warning {
        margin-left: 10px;
        color: orange;
    }

    .history_box {
        max-height: 140px;
        overflow-y: scroll;
    }

    .message_box {
        font-weight: bold;
        color: green;
    }
</style>
    <div class="wrap">
    <div class="icon32"><img width="36px" height="36px"
                             src="<?php echo rtrim( dirname( dirname( $_SERVER[ "REQUEST_URI" ] ) ), '/' ) ?>/wp-content/plugins/wordpress-backup-to-dropbox/Images/WordPressBackupToDropbox_64.png"
                             alt="Wordpress Backup to Dropbox Logo"></div>
<h2><?php _e( 'WordPress Backup to Dropbox' ); ?></h2>
<p class="description"><?php printf( __( 'Version %s' ), BACKUP_TO_DROPBOX_VERSION ) ?></p>
    <?php
        if ( $dropbox->is_authorized() ) {
        $account_info = $dropbox->get_account_info();
        $used = round( $account_info[ 'quota_info' ][ 'normal' ] / 1073741824, 1 );
        $quota = round( $account_info[ 'quota_info' ][ 'quota' ] / 1073741824, 1 );
        ?>
    <h3><?php _e( 'Dropbox Account Details' ); ?></h3>
    <form id="backup_to_dropbox_options" name="backup_to_dropbox_options"
          action="options-general.php?page=backup-to-dropbox" method="post">
        <table class="form-table">
            <tbody>
            <tr>
                <th><?php _e( 'Name' ); ?></th>
                <td><?php echo $account_info[ 'display_name' ] ?></td>
            </tr>
            <tr>
                <th><?php _e( 'Quota' ); ?></th>
                <td><?php echo $used . 'GB of ' . $quota . 'GB (' . round( ( $used / $quota ) * 100, 0 ) . '%)' ?></td>
            </tr>
            </tbody>
        </table>
        <h3><?php _e( 'Next Scheduled' ); ?></h3>
        <?php
        $schedule = $backup->get_schedule();
        if ( $schedule ) {
            ?>
            <p style="margin-left: 10px;"><?php printf( __( 'Next backup scheduled for %s at %s' ), date( 'Y-m-d', $schedule[ 0 ] ), date( 'H:i:s', $schedule[ 0 ] ) ) ?></p>
            <?php } else { ?>
            <p style="margin-left: 10px;"><?php _e( 'No backups are scheduled yet. Please select a day, time and frequency below. ' ) ?></p>
            <?php } ?>
        <h3><?php _e( 'History' ); ?></h3>
        <?php
        $backup_history = $backup->get_history();
        if ( $backup_history ) {
            echo '<div class="history_box">';
            foreach ( $backup_history as $hist ) {
                list( $backup_time, $status, $msg ) = $hist;
                $backup_date = date( 'Y-m-d', $backup_time );
                $backup_time_str = date( 'h:i:s', $backup_time );
                switch ( $status ) {
                    case WP_Backup::BACKUP_STATUS_STARTED:
                        echo "<span class='backup_ok'>" . sprintf( __( 'Backup started on %s at %s' ), $backup_date, $backup_time_str ) . "</span><br />";
                        break;
                    case WP_Backup::BACKUP_STATUS_FINISHED:
                        echo "<span class='backup_ok'>" . sprintf( __( 'Backup completed on %s at %s' ), $backup_date, $backup_time_str ) . "</span><br />";
                        break;
                    case WP_Backup::BACKUP_STATUS_WARNING:
                        echo "<span class='backup_warning'>" . sprintf( __( 'Backup warning on %s at %s: %s' ), $backup_date, $backup_time_str, $msg ) . "</span><br />";
                        break;
                    default:
                        echo "<span class='backup_error'>" . sprintf( __( 'Backup error on %s at %s: %s' ), $backup_date, $backup_time_str, $msg ) . "</span><br />";
                }
            }
            echo '</div>';
        } else {
            echo '<p style="margin-left: 10px;">' . __( 'No backups performed yet' ) . '</p>';
        }
        ?>
        <h3><?php _e( 'Settings' ); ?></h3>
        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row"><label
                        for="dump_location"><?php _e( 'Locally store backup in this folder' ); ?></label></th>
                <td>
                    <input name="dump_location" type="text" id="dump_location" value="<?php echo $dump_location; ?>"
                           class="regular-text code">
                    <span class="description"><?php _e( 'Default is' ); ?><code>wp-content/backups</code></span>
                    <?php if ( $validation_errors && array_key_exists( 'dump_location', $validation_errors ) ) { ?>
                    <br/><span class="description"
                               style="color: red"><?php echo $validation_errors[ 'dump_location' ][ 'message' ] ?></span>
                    <?php } ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label
                        for="dropbox_location"><?php _e( 'Store backup in this folder within Dropbox' ); ?></label>
                </th>
                <td>
                    <input name="dropbox_location" type="text" id="dropbox_location"
                           value="<?php echo $dropbox_location; ?>" class="regular-text code">
                    <span class="description"><?php _e( 'Default is' ); ?><code>WordPressBackup</code></span>
                    <?php if ( $validation_errors && array_key_exists( 'dropbox_location', $validation_errors ) ) { ?>
                    <br/><span class="description"
                               style="color: red"><?php echo $validation_errors[ 'dropbox_location' ][ 'message' ] ?></span>
                    <?php } ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="time"><?php _e( 'Day and Time' ); ?></label></th>
                <td>
                    <select id="day" name="day" <?php echo ( $frequency == 'daily' ) ? 'disabled="disabled"' : '' ?>>
                        <option value="Mon" <?php echo $day == 'Mon' ? ' selected="selected"'
                                : "" ?>><?php _e( 'Monday' ); ?></option>
                        <option value="Tue" <?php echo $day == 'Tue' ? ' selected="selected"'
                                : "" ?>><?php _e( 'Tuesday' ); ?></option>
                        <option value="Wed" <?php echo $day == 'Wed' ? ' selected="selected"'
                                : "" ?>><?php _e( 'Wednesday' ); ?></option>
                        <option value="Thu" <?php echo $day == 'Thu' ? ' selected="selected"'
                                : "" ?>><?php _e( 'Thursday' ); ?></option>
                        <option value="Fri" <?php echo $day == 'Fri' ? ' selected="selected"'
                                : "" ?>><?php _e( 'Friday' ); ?></option>
                        <option value="Sat" <?php echo $day == 'Sat' ? ' selected="selected"'
                                : "" ?>><?php _e( 'Saturday' ); ?></option>
                        <option value="Sun" <?php echo $day == 'Sun' ? ' selected="selected"'
                                : "" ?>><?php _e( 'Sunday' ); ?></option>
                        <?php if ( $frequency == 'daily' ) { ?>
                        <option value="" selected="selected"><?php _e( 'Daily' ); ?></option>
                        <?php } ?>
                    </select> at
                    <select id="time" name="time">
                        <option value="00:00" <?php echo $time == '00:00' ? ' selected="selected"' : "" ?>>00:00
                        </option>
                        <option value="01:00" <?php echo $time == '01:00' ? ' selected="selected"' : "" ?>>01:00
                        </option>
                        <option value="02:00" <?php echo $time == '02:00' ? ' selected="selected"' : "" ?>>02:00
                        </option>
                        <option value="03:00" <?php echo $time == '03:00' ? ' selected="selected"' : "" ?>>03:00
                        </option>
                        <option value="04:00" <?php echo $time == '04:00' ? ' selected="selected"' : "" ?>>04:00
                        </option>
                        <option value="05:00" <?php echo $time == '05:00' ? ' selected="selected"' : "" ?>>05:00
                        </option>
                        <option value="06:00" <?php echo $time == '06:00' ? ' selected="selected"' : "" ?>>06:00
                        </option>
                        <option value="07:00" <?php echo $time == '07:00' ? ' selected="selected"' : "" ?>>07:00
                        </option>
                        <option value="08:00" <?php echo $time == '08:00' ? ' selected="selected"' : "" ?>>08:00
                        </option>
                        <option value="09:00" <?php echo $time == '09:00' ? ' selected="selected"' : "" ?>>09:00
                        </option>
                        <option value="10:00" <?php echo $time == '10:00' ? ' selected="selected"' : "" ?>>10:00
                        </option>
                        <option value="11:00" <?php echo $time == '11:00' ? ' selected="selected"' : "" ?>>11:00
                        </option>
                        <option value="12:00" <?php echo $time == '12:00' ? ' selected="selected"' : "" ?>>12:00
                        </option>
                        <option value="13:00" <?php echo $time == '13:00' ? ' selected="selected"' : "" ?>>13:00
                        </option>
                        <option value="14:00" <?php echo $time == '14:00' ? ' selected="selected"' : "" ?>>14:00
                        </option>
                        <option value="15:00" <?php echo $time == '15:00' ? ' selected="selected"' : "" ?>>15:00
                        </option>
                        <option value="16:00" <?php echo $time == '16:00' ? ' selected="selected"' : "" ?>>16:00
                        </option>
                        <option value="17:00" <?php echo $time == '17:00' ? ' selected="selected"' : "" ?>>17:00
                        </option>
                        <option value="18:00" <?php echo $time == '18:00' ? ' selected="selected"' : "" ?>>18:00
                        </option>
                        <option value="19:00" <?php echo $time == '19:00' ? ' selected="selected"' : "" ?>>19:00
                        </option>
                        <option value="20:00" <?php echo $time == '20:00' ? ' selected="selected"' : "" ?>>20:00
                        </option>
                        <option value="21:00" <?php echo $time == '21:00' ? ' selected="selected"' : "" ?>>21:00
                        </option>
                        <option value="22:00" <?php echo $time == '22:00' ? ' selected="selected"' : "" ?>>22:00
                        </option>
                        <option value="23:00" <?php echo $time == '23:00' ? ' selected="selected"' : "" ?>>23:00
                        </option>
                    </select>
                    <span class="description"><?php _e( 'The day and time the backup to Dropbox is to be performed.' ); ?></span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="frequency"><?php _e( 'Frequency' ); ?></label></th>
                <td>
                    <select id="frequency" name="frequency">
                        <option value="daily" <?php echo $frequency == 'daily' ? ' selected="selected"' : "" ?>>
                            <?php _e( 'Daily' ) ?>
                        </option>
                        <option value="weekly" <?php echo $frequency == 'weekly' ? ' selected="selected"' : "" ?>>
                            <?php _e( 'Weekly' ) ?>
                        </option>
                        <option value="fortnightly" <?php echo $frequency == 'fortnightly' ? ' selected="selected"'
                                : "" ?>>
                            <?php _e( 'Fortnightly' ) ?>
                        </option>
                        <option value="monthly" <?php echo $frequency == 'monthly' ? ' selected="selected"' : "" ?>>
                            <?php _e( 'Every 4 weeks' ) ?>
                        </option>
                        <option value="two_monthly" <?php echo $frequency == 'two_monthly' ? ' selected="selected"'
                                : "" ?>>
                            <?php _e( 'Every 8 weeks' ) ?>
                        </option>
                        <option value="three_monthly" <?php echo $frequency == 'three_monthly' ? ' selected="selected"'
                                : "" ?>>
                            <?php _e( 'Every 12 weeks' ) ?>
                        </option>
                    </select>
                    <span class="description"><?php _e( 'How often the backup to Dropbox is to be performed.' ); ?></span>
                </td>
            </tr>
            </tbody>
        </table>
        <p class="submit">
            <input type="submit" id="save_changes" name="save_changes" class="button-primary"
                   value="<?php _e( 'Save Changes' ); ?>">
            <input type="submit" id="backup_now" name="backup_now" class="button-primary" <?php echo $disable_backup_now
                    ? 'disabled="disabled"' : '' ?> value="<?php _e( 'Backup Now' ); ?>">
            <?php if ( $message ) { ?>
            <span class='message_box'><?php echo $message ?></span>
            <script type="text/javascript">
                jQuery( document ).ready( function ( $ ) {
                    $( '.message_box' ).fadeOut( 2000 );
                } );
            </script>
            <?php } ?>
        </p>
        <?php wp_nonce_field( 'backup_to_dropbox_options_save' ); ?>
    </form>
        <?php

    } else {
        //We need to re authenticate this user
        $url = $dropbox->get_authorize_url();
        ?>
    <h3><?php _e( 'Thank you for installing WordPress Backup to Dropbox!' ); ?></h3>
    <p><?php _e( 'In order to use this plugin you will need to authorized it with your Dropbox account.' ); ?></p>
    <p><?php _e( 'Please click the authorize button below and follow the instructions inside the pop up window.' ); ?></p>
        <?php if ( array_key_exists( 'continue', $_POST ) && !$dropbox->is_authorized() ) { ?>
        <p style="color: red"><?php _e( 'There was an error authorizing the plugin with your Dropbox account. Please try again.' ); ?></p>
            <?php } ?>
    <p>
    <form id="backup_to_dropbox_continue" name="backup_to_dropbox_continue"
          action="options-general.php?page=backup-to-dropbox" method="post">
        <input type="button" name="authorize" id="authorize" value="Authorize"
               onclick="dropbox_authorize( '<?php echo $url ?>' )"/><br/>
        <input style="visibility: hidden;" type="submit" name="continue" id="continue"
               value="<?php _e( 'Continue' ); ?>"/>
    </form>
    </p>
        <?php

    }
} catch ( Exception $e ) {
    echo '<h3>' . __( 'There was a fatal error loading WordPress backup to Dropbox' ) . '</h3>';
    echo '<p>' . $e->getMessage() . '</p>';
}
?>
</div>

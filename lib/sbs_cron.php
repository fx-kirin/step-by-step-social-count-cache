<?php

/*
 * �v���O�C����L�����ɑ��点�悤���Ǝv�������A
 * Facebook��token��feedly��rss���J�X�^�}�C�Y����ݒ��t�������߁A
 * ���m�Ȓl���擾���邽�߂ɂ��ݒ��ʂŃv�����[�h�{�^����t����
 * 
 * ���J����Ă��铊�e��SNS�̃J�E���g���L���b�V������
 * �S�Ă̓��e�̃L���b�V�����擾����ƒ�~����
 * 
 * �S�Ă�SNS�̃J�E���g���擾����ɂ�1�y�[�W�ł�1�`2�b������
 * ���̂���5�A�����Ď擾�����10�b���x�͂�����
 * �L���b�V���̍������Ƃ��������A�����L���O�y�[�W���̂��߂̃v�����[�h�Ȃ̂�
 * 1�x�ɑ����̃L���b�V�����擾���邱�Ƃ��������݂ɏ��ʂ��擾����
 * ���f���x�����T�[�o�ւ̕��ׂ����Ȃ����邱�Ƃ��؂ɂ���
*/
define( 'SBS_ID_FILE', dirname(__FILE__) . "/ids.csv" );
define( 'SBS_POS_FILE', dirname(__FILE__) . "/ids.pos" );
define( 'SBS_GET_LIMIT', 5 );




class SBS_Cron {




	public function __construct() {

		// SBS_Cron�N���X���ŃR���X�g�N�g�ɓo�^���Ă��A�N�e�B�x�[�g�ł͗L���ɂł��Ȃ�����
		// cron�̃A�N�V�����Ŏg���J�X�^���́u���s�Ԋu�v��o�^
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );

		// cron�̃A�N�V������o�^
		add_action( 'sbs_preload_cron', array( $this, 'preload_cron' ) );

	}




	/**
	 * cron�p��5���Ŏ��s�����C�x���g��o�^
	 *
	 */
	public function add_cron_interval( $schedules ) {
	    $schedules['5minutes'] = array( // ������Key��wp_schedule_event�Ŏw�肷����s���o�̓Y���ƂȂ�
	            'interval'  => 300, // �e�X�g�p��60�b
	            'display'   => __( 'Every 5 Minutes' )
	    );
	    return $schedules;
	}




	/**
	 * cron�Ŏ��s����֐����`
	 * 
	 * @global	object		wpdb
	 * 
	 * ID���L�^����CSV���擾
	 * pos�t�@�C����ǂݍ��݁ACSV��pos�t�@�C���̔ԍ��Ő؂���
	 * �؂�����CSV�t�@�C���ɋL�ڂ��ꂽID����A$get_limit�̐������J�E���g���擾
	 * ���ɑΉ�����y�[�WID�̃L���b�V��������ꍇ��΂��Ď���ID��
	 * 
	 * pos�t�@�C����$get_limit + ��΂������C���N�������g
	 * 
	 * pos�t�@�C���̒l��CSV�̍Ō�̍��ڂ܂ł�����cron���~����
	 */
	public function preload_cron() {

		global $wpdb;

		require_once dirname(__FILE__) . '/../sbs-social-count-cache.php';
		$SBS_SCC = new SBS_SocialCountCache();

		$data = file_get_contents( SBS_ID_FILE );
		$id_arr = explode( ",", $data );
		$id_all_count = count( $id_arr );

		$pos_num = file_get_contents( SBS_POS_FILE );
		$id_list = array_slice( $id_arr, $pos_num );
		$pos_count = $pos_num;

		foreach ( $id_list as $postid ) {
			$table_name = $wpdb->prefix . "socal_count_cache";
			$query = "SELECT postid FROM {$table_name} WHERE postid = {$postid}";
			$result = $wpdb->get_row( $query );
			$pos_count++; // pos�t�@�C���p�̃J�E���g�̓L���b�V���������Ă������Ă��i�߂�

			// ���YID�̃L���b�V�����Ȃ��ꍇ
			if( ! isset( $result ) ){
				$url = get_permalink( $postid );
				$SBS_SCC->add_cache( $postid, $url, 'all' );
				$count++;
			}

			// �L���b�V���̎擾�������~�b�g�Ɠ������Ȃ�����cron��1�x�I������
			if( $count == SBS_GET_LIMIT ){
				break;
			}
		}

		file_put_contents( SBS_POS_FILE, $pos_count );

		// ���J�̓��e���ƁApos�t�@�C���̃J�E���g����v������cron����������
		if ( $id_all_count == $pos_count ){
			// �v�����[�h�I������this���g���Ȃ��̂��֐��͎��s�ł��Ȃ��̂Œ��ڋL�q����
			wp_clear_scheduled_hook( 'sbs_preload_cron' );
		}
	}




	/**
	 * cron���J�n�Aids.csv�t�@�C���Aids.pos�t�@�C�����쐬
	 * �C���X�g�[������1�x�������s
	 * 
	 * @global	object		wpdb
	 * 
	 * ids.csv��posts�e�[�u���̃X�e�[�^�X�����J��ID���擾����CSV�`���ŏ����o��
	 * option�ɕۑ����������Aget_option()�Ŏ擾�\�Ȃ̂�400KB�炵���̂ŋp��
	 * 10000post��ID��45KB�B5���|�X�g���炢�܂ł͗]�T��OK���c
	 * �i�I�u�W�F�N�g�L���b�V����1MB�ȉ��̋K�肠��j
	 * 
	 * ids.pos�͓��e���ǂ��܂Ŏ擾���������L�^����|�W�V�����t�@�C��
	 */
	public function start_cron() {

		global $wpdb;

		// ���J�����p�X���[�h�ŕی삳��Ă��Ȃ����e�Ɍ���
		$table_name_posts = $wpdb->prefix . "posts";
		$query = "SELECT ID FROM {$table_name_posts} WHERE post_status = 'publish' AND post_password = ''";
		$result = $wpdb->get_results( $query );

		$result_arr = json_decode( json_encode( $result ), true ); // �I�u�W�F�N�g��A�z�z��ɕϊ�

		foreach( $result_arr as $posts ) {
			foreach( $posts as $id ) {
				$ids[] .= $id;
			}	
		}
		$ids = implode(",", $ids);

		file_put_contents( SBS_ID_FILE, $ids );
		file_put_contents( SBS_POS_FILE, 0 );

		// �����͍ŏ��Ɏ��s���鎞�ԁA���s�Ԋu�A���s���鏈���̃t�b�N��
		// register_activation_hook�ŌĂяo���ƃR�[�f�b�N�X�ɂ���!wp_next_scheduled�����܂���Ə�肭�����Ȃ��̂Œ��Ӂj
		if ( ! wp_next_scheduled( 'sbs_preload_cron' ) ) {
			wp_schedule_event( time(), '5minutes', 'sbs_preload_cron' );
		}
	}




	/**
	 * cron���~����
	 * �A���C���X�g�[�����Ɏ��s
	 * �i�v�����[�h�I������this���g���Ȃ��̂��֐��͎��s�ł��Ȃ��̂Œ��ڋL�q�����j
	 */
	public function stop_cron() {
		wp_clear_scheduled_hook( 'sbs_preload_cron' );
	}
} // SBS_Cron




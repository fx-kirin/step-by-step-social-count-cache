=== Step by Step Social Count Cache ===
Contributors: oxy
Donate link: https://wordpress.org/plugins/step-by-step-social-count-cache/
Tags: cache, count, sns, social
Requires at least: 4.2.4
Tested up to: 4.2.4
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Step by Step Social Count Cache ��SNS�̃J�E���g���L���b�V������v���O�C���ł��B
���e�̍ŏI�X�V������u1���v�u1�T�ԁv�u����ȍ~�v��3�̒i�K�ŁA�L���b�V���̗L��������ݒ肷�邱�Ƃ��ł��܂��B

== Description ==

Step by Step Social Count Cache��SNS�̃J�E���g���L���b�V������v���O�C���ł��B
���e�̍ŏI�X�V������u1���v�u1���`1�T�ԁv�u1�T�Ԉȍ~�v��3�̒i�K�ŁA�L���b�V���̗L��������ݒ肷�邱�Ƃ��ł��܂��B

Facebook�̂����˂��擾�����API�̃o�[�W����2.4�𗘗p���邽�߃I�v�V�����y�[�W��App Token�̓��͂��K�v�ł��B

�J�E���g���擾�ł���SNS��twitter�AFacebook�AGoogle+�A�͂Ăȃu�b�N�}�[�N�APocket�Afeedly��6�ł��B

�f�t�H���g�̗L�������́u1���ȓ��v�̏ꍇ��30���B
�u1���`7���ȓ��v�̏ꍇ��1���B
�u7���ȍ~�v�̏ꍇ��1�T�ԂƂȂ��Ă��܂��B
���ꂼ��̗L�������̓I�v�V�����y�[�W�ŕύX���\�ł��B

�g������Usage�ɂ���ʂ�Asbs_get_all()�Ƃ����^�O��\�����������e�̃��[�v���ɋL�q���܂��B�J�E���g�͔z��ɂȂ��Ă���̂ŁA�K�v��SNS�̓Y���������ďo�͂��Ă��������B

��������sbs_get_twitter()�ȂǁA�ʂ̃J�E���g���擾����^�O���p�ӂ��Ă��܂��B

= �g���� =

1. �Ǘ���ʂ���u�ݒ� �� SBS Social Count Cache�v��I�����܂��B
1. Facebook��App Token�A�J�E���g���L���b�V������SNS�ASNS�̃J�E���g���L���b�V��������Ԃ����ꂼ��ݒ肵�Ă��������B
1. �e���v���[�g�t�@�C���̃��[�v���ňȉ��̂悤�ɋL�q���Ă��������B

**���e�̃L���b�V����S�Ď擾���ď����o�����@**

`<?php
$socal_count = sbs_get_all();
echo $socal_count["twitter"];
echo $socal_count["facebook"];
echo $socal_count["google"];
echo $socal_count["hatena"];
echo $socal_count["pocket"];
echo $socal_count["feedly"];
?>`

**�������͌ʂɎ擾���ď����o�����@**

`<?php
echo sbs_get_twitter();
echo sbs_get_facebook();
echo sbs_get_google();
echo sbs_get_hatena();
echo sbs_get_pocket();
echo sbs_get_feedly();
?>`

== Installation ==

1. �v���O�C���̐V�K�ǉ��{�^�����N���b�N���āA�������ɁuSBS Social Count Cache�v�Ɠ��͂��āu�������C���X�g�[���v���N���b�N���܂��B
1. �������̓_�E�����[�h���ĉ𓀂����t�H���_��`/wp-content/plugins/`�f�B���N�g���ɕۑ����܂��B
1. �ݒ��ʂ̃v���O�C���� **SBS Social Count Cache** ��L���ɂ��Ă��������B
1. �Ǘ���ʂ���u�ݒ� �� SBS Social Count Cache�v��I�����܂��B
1. Facebook��App Token�A�J�E���g���L���b�V������SNS�ASNS�̃J�E���g���L���b�V��������Ԃ����ꂼ��ݒ肵�Ă��������B

== Frequently asked questions ==

-

== Screenshots ==

1. Option page.

== Changelog ==

1.0
���߂̃o�[�W�����B

== Upgrade notice ==

-
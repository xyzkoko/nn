/*
Navicat MySQL Data Transfer

Source Server         : 本机MySQL
Source Server Version : 50553
Source Host           : 127.0.0.1:3306
Source Database       : niuniu

Target Server Type    : MYSQL
Target Server Version : 50553
File Encoding         : 65001

Date: 2018-12-08 23:08:49
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for admin_infos
-- ----------------------------
DROP TABLE IF EXISTS `admin_infos`;
CREATE TABLE `admin_infos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of admin_infos
-- ----------------------------
INSERT INTO `admin_infos` VALUES ('1', 'admin', '21232f297a57a5a743894a0e4a801fc3', null, null);

-- ----------------------------
-- Table structure for game_cards
-- ----------------------------
DROP TABLE IF EXISTS `game_cards`;
CREATE TABLE `game_cards` (
  `id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cards` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int(1) DEFAULT '0',
  `pot` int(11) DEFAULT '0',
  `result` int(11) DEFAULT '0',
  `close_time` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2018102000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of game_cards
-- ----------------------------

-- ----------------------------
-- Table structure for user_bets
-- ----------------------------
DROP TABLE IF EXISTS `user_bets`;
CREATE TABLE `user_bets` (
  `user_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `game_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bets` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `betnum` int(11) DEFAULT '0',
  `result` int(11) DEFAULT '0',
  `created_at` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`user_id`,`game_id`),
  KEY `user_id` (`user_id`),
  KEY `game_id` (`game_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of user_bets
-- ----------------------------

-- ----------------------------
-- Table structure for user_infos
-- ----------------------------
DROP TABLE IF EXISTS `user_infos`;
CREATE TABLE `user_infos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nickname` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `headimgurl` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sex` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`id`),
  KEY `openid` (`openid`)
) ENGINE=MyISAM AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED;

-- ----------------------------
-- Records of user_infos
-- ----------------------------

-- ----------------------------
-- Procedure structure for p_game_cards_d
-- ----------------------------
DROP PROCEDURE IF EXISTS `p_game_cards_d`;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `p_game_cards_d`()
BEGIN
	DECLARE i VARCHAR(10) DEFAULT NULL;
	SET i = date_format(DATE_SUB(now(), interval 7 day),'%Y%m%d');
	DELETE FROM game_cards WHERE id LIKE CONCAT(i,'%');
END
;;
DELIMITER ;

-- ----------------------------
-- Event structure for e_game_cards_d
-- ----------------------------
DROP EVENT IF EXISTS `e_game_cards_d`;
DELIMITER ;;
CREATE DEFINER=`root`@`%` EVENT `e_game_cards_d` ON SCHEDULE EVERY 1 DAY STARTS '2018-12-01 00:00:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL p_game_cards_d()
;;
DELIMITER ;

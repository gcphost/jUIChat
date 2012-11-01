-- phpMyAdmin SQL Dump
-- version 3.3.8
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Oct 30, 2012 at 09:33 PM
-- Server version: 5.1.53
-- PHP Version: 5.3.13

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `livechat`
--

-- --------------------------------------------------------

--
-- Table structure for table `lc_chat`
--

CREATE TABLE IF NOT EXISTS `lc_chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender` varchar(256) NOT NULL,
  `receiver` varchar(256) NOT NULL,
  `chat` longtext NOT NULL,
  `time` varchar(245) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `lc_chat`
--


-- --------------------------------------------------------

--
-- Table structure for table `lc_states`
--

CREATE TABLE IF NOT EXISTS `lc_states` (
  `sender` varchar(256) NOT NULL,
  `receiver` varchar(256) NOT NULL,
  `state` varchar(100) NOT NULL,
  `time` varchar(256) NOT NULL,
  `option` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `lc_states`
--


-- --------------------------------------------------------

--
-- Table structure for table `lc_users`
--

CREATE TABLE IF NOT EXISTS `lc_users` (
  `time` varchar(256) NOT NULL,
  `hash` varchar(256) NOT NULL,
  `user` varchar(256) NOT NULL,
  `email` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `lc_users`
--


-- --------------------------------------------------------

--
-- Table structure for table `lc_visitors`
--

CREATE TABLE IF NOT EXISTS `lc_visitors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(256) NOT NULL,
  `browser` varchar(256) NOT NULL,
  `time` varchar(256) NOT NULL,
  `hash` varchar(256) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `lc_visitors`
--

INSERT INTO `lc_visitors` (`id`, `ip`, `browser`, `time`, `hash`) VALUES
(1, '192.168.1.3', 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27', '1351654383', '19216813508ca0f6b6bac183545969'),
(2, '192.168.1.3', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.4 (KHTML, like Gecko) Chrome/22.0.1229.94 Safari/537.4', '1351654378', '19216813508c803c12f97475949215');

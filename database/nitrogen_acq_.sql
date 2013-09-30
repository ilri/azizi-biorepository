-- phpMyAdmin SQL Dump
-- version 3.5.8.1deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Sep 30, 2013 at 06:54 AM
-- Server version: 5.5.32-0ubuntu0.13.04.1
-- PHP Version: 5.4.9-4ubuntu2.3

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `nitrogen_acq`
--

-- --------------------------------------------------------

--
-- Table structure for table `acquisitions`
--

CREATE TABLE IF NOT EXISTS `acquisitions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `amount_req` int(11) NOT NULL,
  `amount_appr` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`,`project_id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=44 ;

--
-- Dumping data for table `acquisitions`
--

INSERT INTO `acquisitions` (`id`, `user_id`, `project_id`, `date`, `amount_req`, `amount_appr`) VALUES
(2, 2, 3, '2012-08-07', 3, NULL),
(3, 3, 4, '2012-08-08', 6, NULL),
(5, 5, 6, '2012-08-08', 1, NULL),
(7, 6, 3, '2012-08-09', 3, NULL),
(8, 7, 7, '2012-08-10', 3, NULL),
(11, 2, 3, '2012-08-14', 3, NULL),
(12, 6, 3, '2012-08-14', 3, NULL),
(13, 10, 6, '2012-08-15', 3, NULL),
(14, 4, 8, '2012-08-09', 2, NULL),
(15, 4, 8, '2012-08-09', 3, NULL),
(16, 8, 9, '2012-08-10', 3, NULL),
(17, 8, 9, '2012-08-16', 3, NULL),
(18, 11, 10, '2012-08-16', 1, NULL),
(19, 12, 11, '2012-08-17', 10, NULL),
(20, 6, 3, '2012-08-17', 13, NULL),
(21, 13, 12, '2012-08-21', 1, NULL),
(22, 14, 13, '2012-08-22', 1, NULL),
(23, 8, 9, '2012-08-22', 3, NULL),
(24, 15, 14, '2012-08-22', 2, NULL),
(25, 16, 15, '2012-08-23', 10, NULL),
(26, 5, 6, '2012-08-23', 3, NULL),
(27, 17, 6, '2012-08-24', 3, NULL),
(28, 7, 12, '2012-08-24', 2, NULL),
(29, 18, 16, '2012-08-24', 5, NULL),
(30, 14, 13, '2012-08-27', 1, NULL),
(31, 19, 4, '2012-08-28', 35, NULL),
(32, 14, 13, '2012-08-28', 1, NULL),
(33, 14, 13, '2012-08-29', 1, NULL),
(34, 20, 17, '2012-08-29', 3, NULL),
(35, 14, 13, '2012-08-31', 1, NULL),
(36, 21, 12, '2012-09-02', 3, NULL),
(37, 14, 13, '2012-09-04', 2, NULL),
(38, 20, 17, '2012-09-05', 3, NULL),
(39, 20, 17, '2012-09-06', 1, NULL),
(40, 22, 18, '2012-09-06', 3, NULL),
(41, 11, 10, '2012-09-06', 6, NULL),
(42, 23, 19, '2012-09-06', 1, NULL),
(43, 14, 13, '2012-09-07', 2, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE IF NOT EXISTS `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `charge_code` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `charge_code` (`charge_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=20 ;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `charge_code`) VALUES
(3, 'TIITA', 'TIITA-051'),
(4, 'TIITA', 'TIITA-041'),
(6, 'TIITA', 'TIITA-044'),
(7, 'TCIMM', 'TCIMM-1T-02'),
(8, 'BC01, COR', 'BC01-NB0-SWE-010, COR-SWE-01003'),
(9, 'BC01, COR', 'BC01-NB0-SWE-010, COR-SWE-01015'),
(10, 'BC01, COR', 'BC01-NB0-SWE-010, COR-SWE-1009'),
(11, 'Beca', 'Beca-12'),
(12, 'TCIMMYT', 'TCIMMYT-02'),
(13, 'BC01, COR', 'BC01-NB0-SWE-010, COR-SWE-01001'),
(14, 'BC02', 'BC02-SERVIC'),
(15, 'BT03', 'BT03-NB0-HP1001'),
(16, 'Beca', 'Beca-46'),
(17, 'Cip Beca', 'Cip Beca'),
(18, 'BC01', 'BC01-NB0-ATL'),
(19, 'BC01, COR', 'BC01-NB0-SWE-010, COR-SWE-1004');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=24 ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`) VALUES
(2, 'Joshua'),
(3, 'Arthur'),
(4, 'Donfagsiteli'),
(5, 'Sarah'),
(6, 'Anne'),
(7, 'Bridgit'),
(8, 'Cecile Annie'),
(9, 'James Mbora'),
(10, 'Esther'),
(11, 'Dora'),
(12, 'Moses Njahira'),
(13, 'Jacky'),
(14, 'Benius Tukahinua'),
(15, 'Francis'),
(16, 'Charity Muteti'),
(17, 'Ren Mburu'),
(18, 'Tony Maritim'),
(19, 'Teddy Amuga'),
(20, 'Quinata Bukani'),
(21, 'Maureen'),
(22, 'James Wainaina'),
(23, 'Eric');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `acquisitions`
--
ALTER TABLE `acquisitions`
  ADD CONSTRAINT `acquisitions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `acquisitions_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

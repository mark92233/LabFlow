-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 08, 2026 at 03:53 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `snhs_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_assignments`
--

CREATE TABLE `activity_assignments` (
  `AssignmentID` int(11) NOT NULL,
  `ActivityID` int(11) DEFAULT NULL,
  `ClassID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_assignments`
--

INSERT INTO `activity_assignments` (`AssignmentID`, `ActivityID`, `ClassID`) VALUES
(1, 1, 5),
(2, 2, 6),
(3, 2, 6),
(4, 3, 5),
(5, 4, 5),
(6, 5, 5),
(7, 6, 5),
(8, 7, 5),
(9, 8, 5),
(10, 9, 6),
(11, 10, 5),
(12, 10, 6),
(13, 11, 5),
(14, 11, 6),
(15, 12, 5),
(16, 13, 5),
(17, 14, 5),
(18, 15, 5),
(19, 16, 5),
(20, 17, 5),
(21, 18, 5),
(22, 19, 5),
(23, 20, 5),
(24, 21, 5),
(25, 22, 5),
(26, 23, 5),
(27, 24, 5),
(28, 25, 5),
(29, 26, 5),
(30, 27, 5),
(31, 28, 5),
(32, 29, 5),
(33, 30, 5),
(34, 31, 5),
(35, 32, 5),
(36, 33, 5),
(37, 34, 5),
(38, 35, 5),
(39, 36, 5),
(40, 37, 5),
(41, 38, 5),
(42, 39, 5),
(43, 40, 5),
(44, 41, 5),
(45, 42, 5),
(46, 43, 5),
(47, 44, 5),
(48, 45, 5),
(49, 46, 5),
(50, 47, 5),
(51, 48, 5),
(52, 49, 5),
(53, 50, 5),
(54, 51, 5),
(55, 52, 5),
(56, 53, 5),
(57, 54, 5),
(58, 55, 5),
(59, 56, 5),
(60, 57, 5),
(61, 58, 5);

-- --------------------------------------------------------

--
-- Table structure for table `activity_grades`
--

CREATE TABLE `activity_grades` (
  `GradeID` int(11) NOT NULL,
  `ActivityID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `Score` float DEFAULT NULL,
  `Feedback` text DEFAULT NULL,
  `Graded_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_grades`
--

INSERT INTO `activity_grades` (`GradeID`, `ActivityID`, `StudentID`, `Score`, `Feedback`, `Graded_At`) VALUES
(1, 42, 11, 90, NULL, '2026-01-06 11:17:51'),
(2, 42, 19, 90, NULL, '2026-01-06 11:17:51'),
(3, 42, 20, 68, NULL, '2026-01-06 11:17:51'),
(4, 42, 14, 75, NULL, '2026-01-06 11:19:08'),
(5, 42, 18, 0, NULL, '2026-01-06 11:19:08');

-- --------------------------------------------------------

--
-- Table structure for table `activity_groups`
--

CREATE TABLE `activity_groups` (
  `GroupID` int(11) NOT NULL,
  `ActivityID` int(11) NOT NULL,
  `GroupName` varchar(100) NOT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_groups`
--

INSERT INTO `activity_groups` (`GroupID`, `ActivityID`, `GroupName`, `Created_At`) VALUES
(2, 14, 'Group 1', '2026-01-04 07:28:03'),
(3, 15, 'Group 1', '2026-01-04 07:37:03'),
(4, 15, 'group 2', '2026-01-04 07:45:38'),
(5, 15, 'Group 3', '2026-01-04 07:55:23'),
(6, 16, 'Group 1', '2026-01-04 07:57:19'),
(7, 17, 'Group 1', '2026-01-04 08:01:00'),
(8, 17, 'Group 2', '2026-01-04 08:01:00'),
(9, 17, 'Group 3', '2026-01-04 08:01:00'),
(10, 18, 'Group 1', '2026-01-04 08:03:23'),
(11, 18, 'Group 2', '2026-01-04 08:03:23'),
(12, 18, 'Group 3', '2026-01-04 08:03:23'),
(13, 19, 'Group 1', '2026-01-04 08:08:22'),
(14, 19, 'Group 2', '2026-01-04 08:08:22'),
(15, 29, 'Group 1', '2026-01-04 08:28:31'),
(16, 30, 'Group 1', '2026-01-04 08:28:35'),
(17, 31, 'Group 1', '2026-01-04 08:31:19'),
(18, 32, 'Group 1', '2026-01-04 08:34:21'),
(19, 32, 'Group 2', '2026-01-04 08:34:21'),
(20, 32, 'Group 3', '2026-01-04 08:34:21'),
(21, 33, 'Group 1', '2026-01-04 08:35:51'),
(22, 33, 'Group 2', '2026-01-04 08:35:51'),
(23, 34, 'Group 1', '2026-01-04 08:49:03'),
(24, 34, 'Group 2', '2026-01-04 08:49:03'),
(25, 35, 'Group 1', '2026-01-04 10:13:50'),
(26, 35, 'Group 2', '2026-01-04 10:13:50'),
(27, 35, 'Group 3', '2026-01-04 10:16:38'),
(28, 36, 'Group 1', '2026-01-04 10:18:05'),
(29, 36, 'Group 2', '2026-01-04 10:18:05'),
(30, 36, 'Group 3', '2026-01-04 10:18:05'),
(31, 37, 'Group 1', '2026-01-04 10:20:02'),
(32, 37, 'Group 2', '2026-01-04 10:20:56'),
(33, 37, 'group 3', '2026-01-04 10:21:47'),
(34, 38, 'Group 1', '2026-01-04 10:42:03'),
(35, 38, 'Group 2', '2026-01-04 10:42:03'),
(36, 38, 'Group 3', '2026-01-04 10:42:03'),
(37, 39, 'Group 1', '2026-01-04 10:46:59'),
(38, 39, 'Group 2', '2026-01-04 10:46:59'),
(39, 39, 'Group 3', '2026-01-04 10:46:59'),
(40, 40, 'Group 1', '2026-01-04 22:38:45'),
(41, 40, 'Group 2', '2026-01-04 22:38:45'),
(42, 40, 'Group 3', '2026-01-04 22:38:45'),
(43, 40, 'Group 4', '2026-01-04 22:38:45'),
(44, 25, 'Group 1', '2026-01-05 08:47:09'),
(45, 30, 'Group 1', '2026-01-05 08:51:49'),
(46, 41, 'Group 1', '2026-01-05 15:03:37'),
(47, 41, 'Group 2', '2026-01-05 15:03:37'),
(48, 41, 'Group 3', '2026-01-05 15:03:37'),
(49, 42, 'Group 1', '2026-01-06 00:10:43'),
(50, 42, 'Group 2', '2026-01-06 00:10:43'),
(51, 42, 'Group 3', '2026-01-06 00:10:43'),
(52, 44, 'Group 1', '2026-01-07 10:57:03'),
(53, 45, 'Group 1', '2026-01-07 10:57:06'),
(54, 46, 'Group 1', '2026-01-07 10:57:54'),
(55, 47, 'Group 1', '2026-01-07 10:57:57'),
(56, 48, 'Group 1', '2026-01-07 11:01:55'),
(57, 49, 'Group 1', '2026-01-07 11:59:19'),
(58, 50, 'Group 1', '2026-01-07 11:59:27'),
(59, 51, 'Group 1', '2026-01-07 12:02:13'),
(60, 51, 'Group 2', '2026-01-07 12:02:13'),
(61, 51, 'Group 3', '2026-01-07 12:02:13'),
(62, 55, 'Group 1', '2026-01-08 02:19:30'),
(63, 55, 'Group 2', '2026-01-08 02:19:30'),
(64, 55, 'Group 3', '2026-01-08 02:19:30'),
(65, 56, 'Group 1', '2026-01-08 02:20:37'),
(66, 56, 'Group 2', '2026-01-08 02:20:37'),
(67, 56, 'Group 3', '2026-01-08 02:20:37'),
(68, 57, 'Group 1', '2026-01-08 02:34:36'),
(69, 57, 'Group 2', '2026-01-08 02:34:36'),
(70, 57, 'Group 3', '2026-01-08 02:34:36'),
(71, 58, 'Group 1', '2026-01-08 02:46:21'),
(72, 58, 'Group 2', '2026-01-08 02:46:21'),
(73, 58, 'Group 3', '2026-01-08 02:46:21');

-- --------------------------------------------------------

--
-- Table structure for table `activity_requirements`
--

CREATE TABLE `activity_requirements` (
  `RequirementID` int(11) NOT NULL,
  `ActivityID` int(11) NOT NULL,
  `ItemID` int(11) NOT NULL,
  `Required_Qty` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_requirements`
--

INSERT INTO `activity_requirements` (`RequirementID`, `ActivityID`, `ItemID`, `Required_Qty`) VALUES
(1, 1, 3, 1),
(2, 1, 18, 1),
(3, 2, 2, 1),
(4, 2, 22, 1),
(5, 3, 7, 1),
(6, 4, 7, 1),
(7, 4, 22, 1),
(8, 5, 3, 1),
(9, 5, 10, 1),
(10, 5, 17, 1),
(11, 6, 7, 1),
(12, 7, 3, 1),
(13, 7, 10, 1),
(14, 8, 2, 1),
(15, 9, 7, 1),
(16, 9, 21, 1),
(17, 10, 7, 1),
(18, 11, 10, 1),
(19, 11, 21, 1),
(20, 12, 3, 1),
(21, 12, 19, 1),
(22, 13, 18, 1),
(23, 13, 14, 1),
(24, 14, 7, 1),
(25, 15, 19, 1),
(26, 16, 13, 1),
(27, 19, 3, 1),
(28, 32, 8, 1),
(29, 34, 13, 1),
(30, 34, 22, 1),
(31, 35, 7, 1),
(32, 36, 23, 1),
(33, 38, 23, 1),
(34, 39, 23, 1),
(35, 40, 17, 1),
(36, 40, 19, 1),
(37, 40, 21, 1),
(38, 41, 2, 1),
(39, 41, 3, 1),
(40, 41, 17, 1),
(41, 41, 6, 1),
(42, 42, 16, 1),
(43, 42, 3, 1),
(44, 42, 4, 1),
(45, 43, 3, 1),
(46, 51, 8, 1),
(47, 51, 16, 1),
(48, 51, 12, 3),
(49, 52, 3, 1),
(50, 52, 2, 1),
(51, 52, 14, 1),
(52, 53, 16, 1),
(53, 53, 20, 1),
(54, 54, 13, 1),
(55, 54, 9, 1),
(56, 54, 6, 1),
(57, 55, 6, 1),
(58, 55, 11, 1),
(59, 55, 23, 1),
(60, 56, 23, 1),
(61, 56, 4, 1),
(62, 56, 14, 1),
(63, 57, 16, 1),
(64, 57, 2, 1),
(65, 57, 14, 1),
(66, 57, 22, 1),
(67, 57, 18, 1),
(68, 57, 11, 1),
(69, 58, 13, 1),
(70, 58, 2, 1),
(71, 58, 18, 1),
(72, 58, 14, 1),
(73, 58, 11, 1),
(74, 58, 5, 1);

-- --------------------------------------------------------

--
-- Table structure for table `borrowed_items`
--

CREATE TABLE `borrowed_items` (
  `BorrowedItemID` int(11) NOT NULL,
  `SessionID` int(11) NOT NULL,
  `ItemID` int(11) NOT NULL,
  `Possessor_MasterID` int(11) DEFAULT NULL,
  `Quantity` int(11) NOT NULL,
  `Damage_Note` text DEFAULT NULL,
  `Item_Status` enum('Pending','Issued','Returned','Damaged','Lost') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowed_items`
--

INSERT INTO `borrowed_items` (`BorrowedItemID`, `SessionID`, `ItemID`, `Possessor_MasterID`, `Quantity`, `Damage_Note`, `Item_Status`) VALUES
(1, 1, 3, NULL, 1, NULL, 'Pending'),
(2, 1, 18, NULL, 1, NULL, 'Pending'),
(3, 1, 19, NULL, 1, NULL, 'Pending'),
(4, 2, 8, NULL, 1, NULL, 'Pending'),
(5, 2, 7, NULL, 1, NULL, 'Pending'),
(6, 2, 13, NULL, 1, NULL, 'Pending'),
(7, 3, 3, NULL, 1, NULL, 'Pending'),
(9, 5, 17, NULL, 1, NULL, 'Pending'),
(13, 9, 18, NULL, 2, NULL, 'Pending'),
(16, 12, 8, NULL, 1, NULL, 'Pending'),
(17, 13, 2, NULL, 1, NULL, 'Pending'),
(18, 13, 22, NULL, 1, NULL, 'Pending'),
(19, 13, 3, NULL, 1, NULL, 'Pending'),
(20, 14, 2, NULL, 1, NULL, 'Pending'),
(21, 14, 22, NULL, 1, NULL, 'Pending'),
(22, 15, 2, NULL, 1, NULL, 'Pending'),
(23, 15, 22, NULL, 1, NULL, 'Pending'),
(24, 16, 2, NULL, 1, NULL, 'Pending'),
(25, 16, 22, NULL, 1, NULL, 'Pending'),
(26, 17, 7, NULL, 1, NULL, 'Pending'),
(27, 17, 10, NULL, 1, NULL, 'Pending'),
(29, 19, 3, NULL, 1, NULL, 'Pending'),
(30, 19, 10, NULL, 1, NULL, 'Pending'),
(31, 19, 17, NULL, 1, NULL, 'Pending'),
(32, 19, 19, NULL, 1, NULL, 'Pending'),
(33, 19, 12, NULL, 1, NULL, 'Pending'),
(34, 20, 3, NULL, 1, NULL, 'Pending'),
(35, 20, 10, NULL, 1, NULL, 'Pending'),
(36, 20, 17, NULL, 1, NULL, 'Pending'),
(37, 20, 10, NULL, 1, NULL, 'Pending'),
(38, 21, 3, NULL, 1, NULL, 'Pending'),
(39, 21, 10, NULL, 1, NULL, 'Pending'),
(40, 21, 17, NULL, 1, NULL, 'Pending'),
(41, 22, 3, NULL, 1, NULL, 'Pending'),
(42, 22, 10, NULL, 1, NULL, 'Pending'),
(43, 22, 17, NULL, 1, NULL, 'Pending'),
(44, 23, 3, NULL, 1, NULL, 'Pending'),
(45, 23, 10, NULL, 1, NULL, 'Pending'),
(46, 23, 17, NULL, 1, NULL, 'Pending'),
(47, 24, 3, NULL, 1, NULL, 'Pending'),
(48, 24, 10, NULL, 1, NULL, 'Pending'),
(49, 24, 17, NULL, 1, NULL, 'Pending'),
(50, 24, 7, NULL, 1, NULL, 'Pending'),
(51, 25, 7, NULL, 1, NULL, 'Pending'),
(52, 25, 10, NULL, 1, NULL, 'Pending'),
(53, 26, 3, NULL, 1, NULL, 'Pending'),
(54, 27, 3, NULL, 1, NULL, 'Pending'),
(55, 27, 10, NULL, 1, NULL, 'Pending'),
(56, 28, 8, NULL, 1, NULL, 'Pending'),
(57, 29, 7, NULL, 1, NULL, 'Pending'),
(58, 29, 13, NULL, 1, NULL, 'Pending'),
(59, 30, 14, NULL, 1, NULL, 'Pending'),
(60, 30, 23, NULL, 1, NULL, 'Pending'),
(61, 31, 8, NULL, 1, NULL, 'Pending'),
(62, 32, 3, NULL, 1, NULL, 'Pending'),
(63, 33, 8, NULL, 1, NULL, 'Pending'),
(64, 33, 3, NULL, 1, NULL, 'Pending'),
(65, 34, 3, NULL, 1, NULL, 'Pending'),
(66, 34, 10, NULL, 1, NULL, 'Pending'),
(67, 35, 7, NULL, 1, NULL, 'Pending'),
(68, 35, 19, NULL, 1, NULL, 'Pending'),
(69, 35, 21, NULL, 1, NULL, 'Pending'),
(70, 36, 2, NULL, 1, NULL, 'Pending'),
(71, 37, 8, NULL, 1, NULL, 'Pending'),
(72, 37, 3, NULL, 1, NULL, 'Pending'),
(73, 37, 19, NULL, 2, NULL, 'Pending'),
(74, 38, 10, NULL, 1, NULL, 'Pending'),
(75, 38, 21, NULL, 1, NULL, 'Pending'),
(76, 38, 6, NULL, 1, NULL, 'Pending'),
(77, 39, 8, NULL, 1, NULL, 'Pending'),
(78, 40, 19, NULL, 1, NULL, 'Pending'),
(79, 41, 23, NULL, 1, NULL, 'Pending'),
(80, 42, 23, NULL, 1, NULL, 'Pending'),
(81, 43, 7, NULL, 1, NULL, 'Pending'),
(82, 43, 21, NULL, 1, NULL, 'Pending'),
(83, 44, 17, NULL, 1, NULL, 'Pending'),
(84, 44, 19, NULL, 1, NULL, 'Pending'),
(85, 44, 21, NULL, 1, NULL, 'Pending'),
(86, 45, 7, NULL, 1, NULL, 'Pending'),
(87, 46, 23, NULL, 1, NULL, 'Pending'),
(88, 47, 23, NULL, 1, NULL, 'Pending'),
(89, 48, 8, NULL, 1, NULL, 'Pending'),
(90, 49, 23, NULL, 1, NULL, 'Pending'),
(91, 50, 2, NULL, 1, NULL, 'Pending'),
(92, 50, 3, NULL, 1, NULL, 'Pending'),
(93, 50, 17, NULL, 1, NULL, 'Pending'),
(94, 50, 6, NULL, 1, NULL, 'Pending'),
(95, 51, 2, NULL, 1, '', 'Pending'),
(96, 51, 3, NULL, 1, '', 'Pending'),
(97, 51, 17, NULL, 1, '', 'Pending'),
(98, 51, 6, NULL, 1, '', 'Pending'),
(102, 57, 2, 14, 1, NULL, 'Pending'),
(103, 57, 3, 14, 1, NULL, 'Pending'),
(104, 57, 17, 18, 1, NULL, 'Pending'),
(105, 57, 6, 18, 1, NULL, 'Pending'),
(106, 58, 2, NULL, 1, NULL, 'Pending'),
(107, 58, 3, NULL, 1, NULL, 'Pending'),
(108, 58, 17, NULL, 1, NULL, 'Pending'),
(109, 58, 6, NULL, 1, NULL, 'Pending'),
(110, 59, 16, NULL, 1, NULL, 'Pending'),
(111, 59, 3, NULL, 1, NULL, 'Pending'),
(112, 59, 4, NULL, 1, NULL, 'Pending'),
(113, 60, 16, NULL, 1, NULL, 'Pending'),
(114, 60, 3, NULL, 1, NULL, 'Pending'),
(115, 60, 4, NULL, 1, NULL, 'Pending'),
(116, 61, 16, NULL, 1, NULL, 'Pending'),
(117, 61, 3, NULL, 1, NULL, 'Pending'),
(118, 61, 4, NULL, 1, NULL, 'Pending'),
(119, 62, 16, NULL, 1, NULL, 'Pending'),
(120, 62, 3, NULL, 1, NULL, 'Pending'),
(121, 62, 4, NULL, 1, NULL, 'Pending'),
(122, 63, 16, NULL, 1, NULL, 'Pending'),
(123, 63, 3, NULL, 1, NULL, 'Pending'),
(124, 63, 4, NULL, 1, NULL, 'Pending'),
(125, 64, 16, NULL, 1, NULL, 'Pending'),
(126, 64, 3, NULL, 1, NULL, 'Pending'),
(127, 64, 4, NULL, 1, NULL, 'Pending'),
(128, 65, 7, NULL, 1, NULL, 'Pending'),
(129, 66, 16, NULL, 1, NULL, 'Pending'),
(130, 66, 3, NULL, 1, NULL, 'Pending'),
(131, 66, 4, NULL, 1, NULL, 'Pending'),
(132, 67, 16, NULL, 1, NULL, 'Pending'),
(133, 67, 3, NULL, 1, NULL, 'Pending'),
(134, 67, 4, NULL, 1, NULL, 'Pending'),
(135, 68, 8, NULL, 1, NULL, 'Pending'),
(136, 68, 3, NULL, 4, NULL, 'Pending'),
(137, 68, 7, NULL, 1, NULL, 'Pending'),
(138, 68, 10, NULL, 1, NULL, 'Pending'),
(139, 68, 17, NULL, 6, NULL, 'Pending'),
(140, 68, 13, NULL, 1, NULL, 'Pending'),
(141, 68, 18, NULL, 1, NULL, 'Pending'),
(142, 69, 3, NULL, 1, NULL, 'Pending'),
(143, 70, 7, NULL, 1, NULL, 'Pending'),
(144, 70, 8, NULL, 1, NULL, 'Pending'),
(145, 70, 10, NULL, 1, NULL, 'Pending'),
(146, 70, 17, NULL, 3, NULL, 'Pending'),
(147, 70, 3, NULL, 2, NULL, 'Pending'),
(148, 70, 9, NULL, 1, NULL, 'Pending'),
(149, 71, 3, NULL, 1, NULL, 'Pending'),
(150, 72, 8, NULL, 1, NULL, 'Pending'),
(151, 73, 8, NULL, 1, NULL, 'Pending'),
(152, 74, 16, NULL, 1, NULL, 'Pending'),
(153, 74, 12, NULL, 3, NULL, 'Pending'),
(154, 75, 3, NULL, 1, NULL, 'Pending'),
(155, 75, 7, NULL, 1, NULL, 'Pending'),
(156, 75, 13, NULL, 1, NULL, 'Pending'),
(157, 76, 3, NULL, 1, NULL, 'Pending'),
(158, 76, 2, NULL, 1, NULL, 'Pending'),
(159, 76, 14, NULL, 1, NULL, 'Pending'),
(160, 77, 11, NULL, 1, NULL, 'Pending'),
(161, 77, 6, NULL, 1, NULL, 'Pending'),
(162, 78, 23, NULL, 1, NULL, 'Pending'),
(163, 79, 4, NULL, 1, NULL, 'Pending'),
(164, 79, 14, NULL, 1, NULL, 'Pending'),
(165, 80, 16, NULL, 1, NULL, 'Pending'),
(166, 81, 11, NULL, 1, NULL, 'Pending'),
(167, 81, 14, NULL, 1, NULL, 'Pending'),
(168, 81, 18, NULL, 1, NULL, 'Pending'),
(169, 81, 2, NULL, 1, NULL, 'Pending'),
(170, 81, 13, NULL, 1, NULL, 'Pending'),
(171, 82, 5, NULL, 1, NULL, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `borrowing_sessions`
--

CREATE TABLE `borrowing_sessions` (
  `SessionID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `ActivityID` int(11) DEFAULT NULL,
  `QR_Code_Data` varchar(255) DEFAULT NULL,
  `Status` enum('Pending','Approved','Issued','Returned','Cancelled') DEFAULT 'Pending',
  `Remarks` text DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `has_damage` tinyint(1) DEFAULT 0 COMMENT '0=Clean, 1=Has Damaged Items'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowing_sessions`
--

INSERT INTO `borrowing_sessions` (`SessionID`, `StudentID`, `ActivityID`, `QR_Code_Data`, `Status`, `Remarks`, `CreatedAt`, `has_damage`) VALUES
(1, 11, 1, 'QR-SNHS-2F155B-1', 'Returned', 'return', '2025-12-28 08:47:34', 0),
(2, 11, NULL, 'QR-SNHS-8B4D7A-2', 'Returned', 'Test', '2025-12-28 08:57:54', 0),
(3, 11, NULL, 'QR-SNHS-65360E-3', 'Returned', 'Return', '2025-12-28 09:00:52', 0),
(5, 11, NULL, 'QR-SNHS-5F3F87-5', 'Returned', 'Retirn', '2025-12-28 09:04:24', 0),
(9, 11, NULL, 'QR-SNHS-FEF068-9', 'Returned', 'Rerurn', '2025-12-28 09:10:57', 0),
(12, 11, NULL, 'SNHS-REF-6950FE478CBC7', 'Returned', 'Done using', '2025-12-28 09:54:15', 0),
(13, 11, 2, 'SNHS-REF-6950FF91BD032', 'Returned', 'Return', '2025-12-28 09:59:45', 0),
(14, 12, 2, 'SNHS-REF-6950FFD6AFB2C', 'Returned', 'Done', '2025-12-28 10:00:54', 0),
(15, 12, 2, 'SNHS-REF-695105F347B48', 'Returned', 'Done using', '2025-12-28 10:26:59', 0),
(16, 12, 2, 'SNHS-REF-695106D1E26B0', 'Returned', 'Done', '2025-12-28 10:30:41', 0),
(17, 11, 3, 'SNHS-REF-695113989F134', 'Returned', 'Done', '2025-12-28 11:25:12', 0),
(19, 11, 5, 'SNHS-REF-6951D1DFB5789', 'Returned', 'Done using', '2025-12-29 00:57:03', 0),
(20, 11, 5, 'SNHS-REF-6951D33EDAC6D', 'Returned', 'Done', '2025-12-29 01:02:54', 0),
(21, 11, 5, 'SNHS-REF-6951D47EA241E', 'Returned', 'Done', '2025-12-29 01:08:14', 0),
(22, 11, 5, 'SNHS-REF-6951D5539C6A4', 'Returned', 'Done', '2025-12-29 01:11:47', 0),
(23, 11, 5, 'SNHS-REF-6951D5DD1C8FE', 'Returned', 'Done', '2025-12-29 01:14:05', 0),
(24, 11, 5, 'SNHS-REF-6951D6A21C751', 'Returned', 'Done', '2025-12-29 01:17:22', 0),
(25, 11, 6, 'SNHS-REF-6951D88B10DFD', 'Returned', 'Done', '2025-12-29 01:25:31', 0),
(26, 11, NULL, 'SNHS-REF-6951E40FC416F', 'Issued', NULL, '2025-12-29 02:14:39', 0),
(27, 11, NULL, 'SNHS-REF-69532FCDCD676', 'Returned', 'Returned with reported damages', '2025-12-30 01:50:05', 1),
(28, 11, NULL, 'SNHS-REF-695333A652A06', 'Issued', NULL, '2025-12-30 02:06:30', 0),
(29, 11, NULL, 'SNHS-REF-695333C5CDAF4', 'Returned', 'Returned with reported damages', '2025-12-30 02:07:01', 1),
(30, 11, NULL, 'SNHS-REF-69533439E85A2', 'Returned', 'Returned with reported damages', '2025-12-30 02:08:57', 1),
(31, 11, NULL, 'SNHS-REF-695334A6D0872', 'Returned', 'Returned in good condition', '2025-12-30 02:10:46', 0),
(32, 11, NULL, 'SNHS-REF-695334FE711F7', 'Returned', 'Returned in good condition', '2025-12-30 02:12:14', 0),
(33, 11, NULL, 'SNHS-REF-695335F806019', 'Returned', 'Returned in good condition', '2025-12-30 02:16:24', 0),
(34, 11, NULL, 'SNHS-REF-6953375BCB2A8', 'Returned', 'Returned with damages', '2025-12-30 02:22:19', 1),
(35, 11, NULL, 'SNHS-REF-6953380140981', 'Returned', 'Returned with damages', '2025-12-30 02:25:05', 1),
(36, 11, 8, 'SNHS-REF-695742C7A7F52', 'Approved', NULL, '2026-01-02 04:00:07', 0),
(37, 11, NULL, 'SNHS-REF-69587998E1FE7', 'Returned', 'Returned with damages', '2026-01-03 02:06:16', 1),
(38, 11, 11, 'SNHS-REF-6958832119B80', 'Returned', 'Returned with damages', '2026-01-03 02:46:57', 1),
(39, 15, NULL, 'SNHS-REF-6958A74D95391', 'Pending', NULL, '2026-01-03 05:21:17', 0),
(40, 11, 15, 'SNHS-REF-695A18C536CEE', 'Pending', NULL, '2026-01-04 07:37:41', 0),
(41, 11, 39, 'SNHS-REF-695A4B0CC0849', 'Returned', 'Returned in good condition', '2026-01-04 11:12:12', 0),
(42, 11, NULL, 'SNHS-REF-695A5F2C398DA', 'Returned', 'Returned in good condition', '2026-01-04 12:38:04', 0),
(43, 11, NULL, 'SNHS-REF-695AE9B9E08A5', 'Returned', 'Returned with damages', '2026-01-04 22:29:13', 1),
(44, 11, 40, 'SNHS-REF-695B03D841488', 'Pending', NULL, '2026-01-05 00:20:40', 0),
(45, 11, 35, 'SNHS-REF-695B7AA13EAFA', 'Returned', 'Returned in good condition', '2026-01-05 08:47:29', 0),
(46, 19, 39, 'SNHS-REF-695B8D785BAEC', 'Returned', 'Returned in good condition', '2026-01-05 10:07:52', 0),
(47, 17, 39, 'SNHS-REF-695B8D8E07897', 'Returned', 'Returned in good condition', '2026-01-05 10:08:14', 0),
(48, 11, 32, 'SNHS-REF-695BCFF5A7F95', 'Pending', NULL, '2026-01-05 14:51:33', 0),
(49, 11, 38, 'SNHS-REF-695BD0CEE1157', 'Pending', NULL, '2026-01-05 14:55:10', 0),
(50, 19, 41, 'SNHS-REF-695BD3C0D4B47', 'Pending', NULL, '2026-01-05 15:07:44', 0),
(51, 11, 41, 'SNHS-REF-695BD4E73F4B3', 'Returned', NULL, '2026-01-05 15:12:39', 0),
(57, 14, 41, NULL, 'Returned', 'Returned in good condition', '2026-01-05 16:41:05', 0),
(58, 18, 41, 'SNHS-REF-695BEAA420DCD', 'Returned', 'Returned in good condition', '2026-01-05 16:45:24', 0),
(59, 11, 42, 'SNHS-REF-695C5323AC314', 'Returned', 'Returned in good condition', '2026-01-06 00:11:15', 0),
(60, 20, 42, 'SNHS-REF-695C5346620CD', 'Returned', 'Returned in good condition', '2026-01-06 00:11:50', 0),
(61, 19, 42, 'SNHS-REF-695C536E2D27E', 'Returned', 'Returned in good condition', '2026-01-06 00:12:30', 0),
(62, 11, 42, 'SNHS-REF-695C59F53A949', 'Returned', 'Returned in good condition', '2026-01-06 00:40:21', 0),
(63, 16, 42, 'SNHS-REF-695CC5405C393', 'Returned', 'Returned in good condition', '2026-01-06 08:18:08', 0),
(64, 17, 42, 'SNHS-REF-695CC55B2F7E3', 'Returned', 'Returned in good condition', '2026-01-06 08:18:35', 0),
(65, 16, 3, 'SNHS-REF-695CCBD266663', 'Returned', 'Returned with damages', '2026-01-06 08:46:10', 1),
(66, 14, 42, 'SNHS-REF-695CCC7C5450A', 'Returned', 'Returned in good condition', '2026-01-06 08:49:00', 0),
(67, 18, 42, 'SNHS-REF-695CCC911B54C', 'Returned', 'Returned in good condition', '2026-01-06 08:49:21', 0),
(68, 11, NULL, 'SNHS-REF-695DAAC9EAC93', 'Pending', NULL, '2026-01-07 00:37:29', 0),
(69, 11, NULL, 'SNHS-REF-695DAB2C6F873', 'Pending', NULL, '2026-01-07 00:39:08', 0),
(70, 11, NULL, 'SNHS-REF-695DAC60F0213', 'Pending', NULL, '2026-01-07 00:44:16', 0),
(71, 11, 43, 'SNHS-REF-695DB17B77D6B', 'Returned', 'Returned in good condition', '2026-01-07 01:06:03', 0),
(72, 14, 51, 'SNHS-REF-695E63558B11B', 'Pending', NULL, '2026-01-07 13:44:53', 0),
(73, 11, 51, 'SNHS-REF-695E6682C2A0A', 'Returned', 'Returned with damages', '2026-01-07 13:58:26', 1),
(74, 18, 51, 'SNHS-REF-695E66A292583', 'Returned', 'Returned in good condition', '2026-01-07 13:58:58', 0),
(75, 21, NULL, 'SNHS-REF-695F10E42E4B6', 'Returned', 'Returned with damages', '2026-01-08 02:05:24', 1),
(76, 21, 52, 'SNHS-REF-695F12C0EB4B3', 'Returned', 'Returned in good condition', '2026-01-08 02:13:20', 0),
(77, 18, 55, 'SNHS-REF-695F1572C2B1C', 'Pending', NULL, '2026-01-08 02:24:50', 0),
(78, 14, 56, 'SNHS-REF-695F16436E8F1', 'Returned', 'Returned in good condition', '2026-01-08 02:28:19', 0),
(79, 11, 56, 'SNHS-REF-695F166AC676B', 'Returned', 'Returned in good condition', '2026-01-08 02:28:58', 0),
(80, 11, 57, 'SNHS-REF-695F18037BC29', 'Pending', NULL, '2026-01-08 02:35:47', 0),
(81, 11, 58, 'SNHS-REF-695F1B8F12648', 'Pending', NULL, '2026-01-08 02:50:55', 0),
(82, 14, 58, 'SNHS-REF-695F1BB4F36ED', 'Pending', NULL, '2026-01-08 02:51:32', 0);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `CategoryID` int(11) NOT NULL,
  `Category_Name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`CategoryID`, `Category_Name`) VALUES
(3, 'Measuring Instruments'),
(4, 'Heating Equipment'),
(5, 'Glassware'),
(6, 'Transfer & Handling Tools'),
(7, 'Safety Equipment'),
(8, 'Chemicals');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `ClassID` int(11) NOT NULL,
  `TeacherID` int(11) NOT NULL,
  `Class_Name` varchar(100) NOT NULL,
  `Section` varchar(50) DEFAULT NULL,
  `Semester` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`ClassID`, `TeacherID`, `Class_Name`, `Section`, `Semester`) VALUES
(5, 9, 'General Chem', '11 STEM A', '2nd Semester'),
(6, 9, 'Science 10', '10 Newton', '1st Semester');

-- --------------------------------------------------------

--
-- Table structure for table `class_enrollment`
--

CREATE TABLE `class_enrollment` (
  `EnrollmentID` int(11) NOT NULL,
  `ClassID` int(11) NOT NULL,
  `MasterID` int(11) DEFAULT NULL,
  `ClearanceStatus` enum('Pending','Cleared') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_enrollment`
--

INSERT INTO `class_enrollment` (`EnrollmentID`, `ClassID`, `MasterID`, `ClearanceStatus`) VALUES
(1, 5, 12, 'Pending'),
(2, 6, 13, 'Pending'),
(3, 5, 14, 'Pending'),
(4, 6, 15, 'Pending'),
(5, 5, 16, 'Pending'),
(6, 5, 17, 'Pending'),
(7, 5, 18, 'Pending'),
(8, 5, 19, 'Pending'),
(9, 5, 20, 'Pending'),
(10, 5, 21, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `damaged_returns`
--

CREATE TABLE `damaged_returns` (
  `damage_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `qty_damaged` int(11) NOT NULL,
  `damage_type` enum('Broken','Lost','Dirty','Malfunction') DEFAULT 'Broken',
  `status` enum('Unresolved','Under Review','Resolved') DEFAULT 'Unresolved',
  `notes` text DEFAULT NULL,
  `logged_at` datetime DEFAULT current_timestamp(),
  `proof_image` varchar(255) DEFAULT NULL,
  `evidence_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `damaged_returns`
--

INSERT INTO `damaged_returns` (`damage_id`, `session_id`, `item_id`, `student_id`, `qty_damaged`, `damage_type`, `status`, `notes`, `logged_at`, `proof_image`, `evidence_image`) VALUES
(1, 27, 3, 11, 1, 'Broken', 'Unresolved', 'broken', '2025-12-30 10:05:06', NULL, NULL),
(2, 29, 13, 11, 1, 'Broken', 'Unresolved', 'brake', '2025-12-30 10:08:17', NULL, NULL),
(3, 30, 14, 11, 1, 'Lost', 'Unresolved', '', '2025-12-30 10:10:10', NULL, NULL),
(4, 34, 3, 11, 1, 'Lost', 'Resolved', 'lost', '2025-12-30 10:22:59', 'proof_4_1767351033.jpg', NULL),
(5, 35, 7, 11, 1, 'Broken', 'Unresolved', '', '2025-12-30 10:26:42', NULL, NULL),
(6, 35, 19, 11, 1, 'Lost', 'Unresolved', '', '2025-12-30 10:26:42', NULL, NULL),
(7, 37, 8, 11, 1, 'Broken', 'Resolved', '', '2026-01-03 10:44:23', 'proof_7_1767408306.jpg', 'dmg_37_8_1767408263.jpeg'),
(8, 38, 10, 11, 1, 'Lost', 'Resolved', '', '2026-01-03 10:48:11', 'proof_8_1767408579.png', 'dmg_38_10_1767408491.jpg'),
(9, 43, 7, 11, 1, 'Broken', 'Resolved', '', '2026-01-05 06:30:09', 'proof_9_1767565856.png', 'dmg_43_7_1767565809.jpg'),
(10, 65, 7, 16, 1, 'Broken', 'Unresolved', '', '2026-01-06 16:46:57', NULL, 'dmg_65_7_1767689217.png'),
(11, 73, 8, 11, 1, 'Broken', 'Unresolved', '', '2026-01-07 22:00:31', NULL, 'dmg_73_8_1767794431.png'),
(12, 75, 3, 21, 1, 'Broken', 'Resolved', '', '2026-01-08 10:07:19', 'proof_12_1767838081.jpg', 'dmg_75_3_1767838039.png');

-- --------------------------------------------------------

--
-- Table structure for table `group_logistics`
--

CREATE TABLE `group_logistics` (
  `LogisticsID` int(11) NOT NULL,
  `ActivityID` int(11) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `ItemID` int(11) NOT NULL,
  `AssignedToMasterID` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL,
  `AssignedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_logistics`
--

INSERT INTO `group_logistics` (`LogisticsID`, `ActivityID`, `GroupID`, `ItemID`, `AssignedToMasterID`, `Quantity`, `AssignedAt`) VALUES
(6, 51, 59, 8, 14, 1, '2026-01-07 13:44:15'),
(7, 51, 59, 16, 17, 1, '2026-01-07 13:44:16'),
(8, 51, 61, 12, 19, 3, '2026-01-07 13:52:41'),
(9, 51, 61, 16, 19, 1, '2026-01-07 13:52:42'),
(10, 51, 61, 8, 19, 1, '2026-01-07 13:52:43'),
(11, 51, 60, 8, 12, 1, '2026-01-07 13:57:56'),
(12, 51, 60, 16, 18, 1, '2026-01-07 13:57:57'),
(13, 51, 60, 12, 18, 3, '2026-01-07 13:57:58'),
(14, 55, 62, 23, 17, 1, '2026-01-08 02:22:01'),
(15, 55, 62, 11, 18, 1, '2026-01-08 02:22:02'),
(16, 55, 62, 6, 18, 1, '2026-01-08 02:22:06'),
(17, 55, 63, 23, 21, 1, '2026-01-08 02:25:42'),
(18, 55, 63, 11, 20, 1, '2026-01-08 02:25:45'),
(19, 55, 63, 6, 20, 1, '2026-01-08 02:25:49'),
(20, 56, 67, 23, 14, 1, '2026-01-08 02:27:38'),
(21, 56, 67, 4, 12, 1, '2026-01-08 02:27:40'),
(22, 56, 67, 14, 12, 1, '2026-01-08 02:27:41'),
(23, 55, 64, 23, 16, 1, '2026-01-08 02:32:37'),
(24, 55, 64, 6, 14, 1, '2026-01-08 02:32:38'),
(25, 55, 64, 11, 14, 1, '2026-01-08 02:32:39'),
(26, 57, 69, 16, 12, 1, '2026-01-08 02:35:27'),
(27, 57, 69, 2, 20, 1, '2026-01-08 02:35:28'),
(28, 57, 69, 14, 20, 1, '2026-01-08 02:35:29'),
(29, 57, 69, 22, 20, 1, '2026-01-08 02:35:31'),
(30, 57, 69, 18, 20, 1, '2026-01-08 02:35:33'),
(31, 57, 69, 11, 20, 1, '2026-01-08 02:35:35'),
(32, 57, 68, 16, 21, 1, '2026-01-08 02:39:30'),
(33, 57, 68, 2, 18, 1, '2026-01-08 02:39:31'),
(34, 57, 68, 14, 18, 1, '2026-01-08 02:39:32'),
(35, 57, 68, 22, 18, 1, '2026-01-08 02:39:33'),
(36, 57, 68, 18, 18, 1, '2026-01-08 02:39:35'),
(37, 57, 68, 11, 18, 1, '2026-01-08 02:39:37'),
(38, 58, 72, 13, 21, 1, '2026-01-08 02:47:25'),
(39, 58, 72, 2, 21, 1, '2026-01-08 02:47:27'),
(40, 58, 72, 18, 21, 1, '2026-01-08 02:47:29'),
(41, 58, 72, 14, 21, 1, '2026-01-08 02:47:30'),
(42, 58, 72, 11, 21, 1, '2026-01-08 02:47:32'),
(43, 58, 72, 5, 21, 1, '2026-01-08 02:47:34'),
(44, 58, 73, 5, 14, 1, '2026-01-08 02:50:38'),
(45, 58, 73, 11, 12, 1, '2026-01-08 02:50:42'),
(46, 58, 73, 14, 12, 1, '2026-01-08 02:50:43'),
(47, 58, 73, 18, 12, 1, '2026-01-08 02:50:45'),
(48, 58, 73, 2, 12, 1, '2026-01-08 02:50:46'),
(49, 58, 73, 13, 12, 1, '2026-01-08 02:50:47');

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `MemberID` int(11) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `MasterID` int(11) NOT NULL COMMENT 'Links to lookup_masterlist.MasterID',
  `Is_Leader` tinyint(1) DEFAULT 0,
  `Joined_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`MemberID`, `GroupID`, `MasterID`, `Is_Leader`, `Joined_At`) VALUES
(3, 2, 12, 1, '2026-01-04 07:28:03'),
(4, 2, 14, 0, '2026-01-04 07:28:03'),
(5, 3, 12, 1, '2026-01-04 07:37:03'),
(6, 3, 14, 0, '2026-01-04 07:37:03'),
(7, 4, 17, 1, '2026-01-04 07:45:38'),
(8, 4, 16, 0, '2026-01-04 07:45:38'),
(9, 5, 18, 1, '2026-01-04 07:55:23'),
(10, 5, 19, 0, '2026-01-04 07:55:23'),
(11, 6, 18, 0, '2026-01-04 07:57:19'),
(12, 6, 19, 0, '2026-01-04 07:57:19'),
(13, 6, 12, 0, '2026-01-04 07:57:19'),
(14, 6, 14, 1, '2026-01-04 07:57:19'),
(15, 6, 16, 0, '2026-01-04 07:57:19'),
(16, 6, 17, 0, '2026-01-04 07:57:19'),
(17, 7, 18, 1, '2026-01-04 08:01:00'),
(18, 8, 19, 1, '2026-01-04 08:01:00'),
(19, 9, 12, 1, '2026-01-04 08:01:00'),
(20, 9, 14, 0, '2026-01-04 08:01:00'),
(21, 8, 16, 0, '2026-01-04 08:01:00'),
(22, 7, 17, 0, '2026-01-04 08:01:00'),
(23, 10, 18, 1, '2026-01-04 08:03:23'),
(24, 11, 19, 1, '2026-01-04 08:03:23'),
(25, 12, 12, 1, '2026-01-04 08:03:23'),
(26, 12, 14, 0, '2026-01-04 08:03:23'),
(27, 11, 16, 0, '2026-01-04 08:03:23'),
(28, 10, 17, 0, '2026-01-04 08:03:23'),
(29, 13, 18, 1, '2026-01-04 08:08:22'),
(30, 14, 19, 1, '2026-01-04 08:08:22'),
(31, 14, 12, 0, '2026-01-04 08:08:22'),
(32, 13, 14, 0, '2026-01-04 08:08:22'),
(33, 13, 16, 0, '2026-01-04 08:08:22'),
(34, 14, 17, 0, '2026-01-04 08:08:22'),
(35, 18, 18, 0, '2026-01-04 08:34:21'),
(36, 18, 14, 0, '2026-01-04 08:34:21'),
(37, 19, 12, 0, '2026-01-04 08:34:21'),
(38, 19, 17, 0, '2026-01-04 08:34:21'),
(39, 20, 19, 0, '2026-01-04 08:34:21'),
(40, 20, 16, 0, '2026-01-04 08:34:21'),
(41, 21, 18, 1, '2026-01-04 08:35:51'),
(42, 21, 12, 0, '2026-01-04 08:35:51'),
(43, 21, 17, 0, '2026-01-04 08:35:51'),
(44, 22, 14, 0, '2026-01-04 08:35:51'),
(45, 22, 19, 0, '2026-01-04 08:35:51'),
(46, 23, 18, 0, '2026-01-04 08:49:03'),
(47, 23, 14, 1, '2026-01-04 08:49:03'),
(48, 23, 17, 0, '2026-01-04 08:49:03'),
(49, 24, 12, 1, '2026-01-04 08:49:03'),
(50, 24, 19, 0, '2026-01-04 08:49:03'),
(51, 24, 16, 0, '2026-01-04 08:49:03'),
(52, 25, 19, 1, '2026-01-04 10:13:50'),
(53, 26, 12, 1, '2026-01-04 10:13:50'),
(54, 26, 14, 0, '2026-01-04 10:13:50'),
(55, 25, 16, 0, '2026-01-04 10:13:50'),
(56, 25, 17, 0, '2026-01-04 10:13:50'),
(57, 26, 18, 0, '2026-01-04 10:13:50'),
(58, 27, 20, 1, '2026-01-04 10:16:38'),
(59, 28, 19, 1, '2026-01-04 10:18:05'),
(60, 29, 20, 1, '2026-01-04 10:18:05'),
(61, 30, 12, 1, '2026-01-04 10:18:05'),
(62, 30, 14, 0, '2026-01-04 10:18:05'),
(63, 29, 16, 0, '2026-01-04 10:18:05'),
(64, 28, 17, 0, '2026-01-04 10:18:05'),
(65, 28, 18, 0, '2026-01-04 10:18:05'),
(66, 31, 12, 1, '2026-01-04 10:20:02'),
(67, 31, 14, 0, '2026-01-04 10:20:02'),
(68, 32, 18, 1, '2026-01-04 10:20:56'),
(69, 32, 16, 0, '2026-01-04 10:20:56'),
(70, 32, 19, 0, '2026-01-04 10:20:56'),
(71, 33, 17, 1, '2026-01-04 10:21:47'),
(72, 33, 20, 0, '2026-01-04 10:21:47'),
(73, 34, 18, 0, '2026-01-04 10:42:03'),
(74, 34, 14, 1, '2026-01-04 10:42:03'),
(75, 34, 19, 0, '2026-01-04 10:42:03'),
(76, 34, 12, 0, '2026-01-04 10:42:03'),
(77, 35, 20, 1, '2026-01-04 10:42:03'),
(78, 36, 17, 1, '2026-01-04 10:42:03'),
(79, 37, 18, 1, '2026-01-04 10:46:59'),
(80, 37, 16, 0, '2026-01-04 10:46:59'),
(81, 38, 14, 1, '2026-01-04 10:46:59'),
(82, 38, 20, 0, '2026-01-04 10:46:59'),
(83, 39, 12, 1, '2026-01-04 10:46:59'),
(84, 39, 19, 0, '2026-01-04 10:46:59'),
(85, 39, 17, 0, '2026-01-04 10:46:59'),
(86, 40, 12, 1, '2026-01-04 22:38:45'),
(87, 41, 14, 1, '2026-01-04 22:38:45'),
(88, 42, 16, 1, '2026-01-04 22:38:45'),
(89, 43, 17, 1, '2026-01-04 22:38:45'),
(90, 43, 18, 0, '2026-01-04 22:38:45'),
(91, 42, 19, 0, '2026-01-04 22:38:45'),
(92, 41, 20, 0, '2026-01-04 22:38:45'),
(93, 44, 12, 1, '2026-01-05 08:47:09'),
(94, 44, 14, 0, '2026-01-05 08:47:09'),
(95, 45, 12, 1, '2026-01-05 08:51:49'),
(96, 45, 14, 0, '2026-01-05 08:51:49'),
(97, 46, 12, 1, '2026-01-05 15:03:37'),
(98, 47, 14, 1, '2026-01-05 15:03:37'),
(99, 48, 16, 1, '2026-01-05 15:03:37'),
(100, 48, 17, 0, '2026-01-05 15:03:37'),
(101, 47, 18, 0, '2026-01-05 15:03:37'),
(102, 46, 19, 0, '2026-01-05 15:03:37'),
(103, 46, 20, 0, '2026-01-05 15:03:37'),
(104, 49, 12, 1, '2026-01-06 00:10:43'),
(105, 50, 14, 1, '2026-01-06 00:10:43'),
(106, 51, 16, 1, '2026-01-06 00:10:43'),
(107, 51, 17, 0, '2026-01-06 00:10:43'),
(108, 50, 18, 0, '2026-01-06 00:10:43'),
(109, 49, 19, 0, '2026-01-06 00:10:43'),
(110, 49, 20, 0, '2026-01-06 00:10:43'),
(111, 59, 17, 1, '2026-01-07 12:02:13'),
(112, 60, 18, 1, '2026-01-07 12:02:13'),
(113, 61, 19, 1, '2026-01-07 12:02:13'),
(114, 61, 20, 0, '2026-01-07 12:02:13'),
(115, 60, 12, 0, '2026-01-07 12:02:13'),
(116, 59, 14, 0, '2026-01-07 12:02:13'),
(117, 59, 16, 0, '2026-01-07 12:02:13'),
(118, 62, 18, 0, '2026-01-08 02:19:30'),
(119, 62, 12, 1, '2026-01-08 02:19:30'),
(120, 62, 17, 0, '2026-01-08 02:19:30'),
(121, 63, 20, 0, '2026-01-08 02:19:30'),
(122, 63, 19, 0, '2026-01-08 02:19:30'),
(123, 63, 21, 1, '2026-01-08 02:19:30'),
(124, 64, 14, 1, '2026-01-08 02:19:30'),
(125, 64, 16, 0, '2026-01-08 02:19:30'),
(126, 65, 20, 1, '2026-01-08 02:20:37'),
(127, 66, 21, 1, '2026-01-08 02:20:37'),
(128, 67, 12, 1, '2026-01-08 02:20:37'),
(129, 67, 14, 0, '2026-01-08 02:20:37'),
(130, 66, 16, 0, '2026-01-08 02:20:37'),
(131, 65, 17, 0, '2026-01-08 02:20:37'),
(132, 65, 18, 0, '2026-01-08 02:20:37'),
(133, 66, 19, 0, '2026-01-08 02:20:37'),
(134, 68, 18, 0, '2026-01-08 02:34:36'),
(135, 68, 21, 1, '2026-01-08 02:34:36'),
(136, 68, 16, 0, '2026-01-08 02:34:36'),
(137, 69, 20, 0, '2026-01-08 02:34:36'),
(138, 69, 12, 1, '2026-01-08 02:34:36'),
(139, 69, 19, 0, '2026-01-08 02:34:36'),
(140, 70, 14, 0, '2026-01-08 02:34:36'),
(141, 70, 17, 1, '2026-01-08 02:34:36'),
(142, 71, 20, 1, '2026-01-08 02:46:21'),
(143, 72, 21, 1, '2026-01-08 02:46:21'),
(144, 73, 12, 1, '2026-01-08 02:46:21'),
(145, 73, 14, 0, '2026-01-08 02:46:21'),
(146, 72, 16, 0, '2026-01-08 02:46:21'),
(147, 71, 17, 0, '2026-01-08 02:46:21'),
(148, 71, 18, 0, '2026-01-08 02:46:21'),
(149, 72, 19, 0, '2026-01-08 02:46:21');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `ItemID` int(11) NOT NULL,
  `CategoryID` int(11) NOT NULL,
  `Item_Name` varchar(100) NOT NULL,
  `Total_Qty` int(11) DEFAULT 0,
  `Available_Qty` int(11) DEFAULT 0,
  `Location` varchar(100) DEFAULT NULL,
  `Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`ItemID`, `CategoryID`, `Item_Name`, `Total_Qty`, `Available_Qty`, `Location`, `Description`) VALUES
(2, 3, 'Graduated cylinder', 10, 9, 'Cabinet B - Shelf 3', 'Used to accurately measure the volume of liquids.'),
(3, 3, 'Beaker', 15, 9, 'Cabinet B - Shelf 3', 'A container used for holding, mixing, and roughly measuring liquids.'),
(4, 3, 'Thermometer', 8, 8, 'Cabinet B - Shelf 3', 'Measures the temperature of substances.'),
(5, 3, 'Triple beam balance', 15, 15, 'Cabinet B - Shelf 3', 'Measures the mass of objects precisely.'),
(6, 3, 'Ruler / meter stick', 20, 19, 'Cabinet B - Shelf 3', 'Measures length or distance of objects.'),
(7, 4, 'Bunsen burner', 10, 6, 'Cabinet E - Shelf 1', 'Produces a flame for heating substances in experiments.'),
(8, 4, 'Alcohol lamp', 30, 26, 'Cabinet E - Shelf 1', 'Provides a small flame for gentle heating.'),
(9, 4, 'Hot plate', 10, 10, 'Cabinet B - Shelf 3', 'Electrically heats substances without an open flame.'),
(10, 4, 'Crucible', 20, 18, 'Cabinet E - Shelf 1', 'Holds substances that are heated to very high temperatures.'),
(11, 4, 'Wire gauze', 50, 50, 'Cabinet E - Shelf 1', 'Supports glassware while distributing heat evenly.'),
(12, 5, 'Test tube', 60, 60, 'Cabinet A - Shelf 5', 'Holds small amounts of substances for testing or heating.'),
(13, 5, 'Erlenmeyer flask', 35, 34, 'Cabinet A - Shelf 5', 'Used for mixing solutions with minimal spilling.'),
(14, 5, 'Volumetric flask', 30, 29, 'Cabinet A - Shelf 5', 'Measures an exact volume of liquid for solutions.'),
(15, 5, 'Watch glass', 70, 70, 'Cabinet A - Shelf 5', 'Used to hold solids or cover beakers.'),
(16, 5, 'Glass stirring rod', 50, 50, 'Cabinet A - Shelf 5', 'Used to mix liquids or guide pouring'),
(17, 6, 'Dropper / pipette', 100, 99, 'Cabinet D - Shelf 2', 'Transfers small amounts of liquid accurately.'),
(18, 6, 'Tongs', 40, 40, 'Cabinet D - Shelf 2', 'Safely holds hot or hazardous objects'),
(19, 6, 'Forceps', 40, 39, 'Cabinet D - Shelf 2', 'Picks up small objects precisely.'),
(20, 6, 'Spatula', 30, 30, 'Cabinet D - Shelf 2', 'Transfers small amounts of solid chemicals.'),
(21, 6, 'Funnel', 30, 30, 'Cabinet D - Shelf 2', 'Helps pour liquids or solids into containers without spilling.'),
(22, 7, 'Safety goggles', 30, 30, 'Cabinet C - Shelf 4', 'Protects the eyes from chemical splashes or debris.'),
(23, 7, 'Gloves', 200, 199, 'Cabinet C - Shelf 4', 'Shields hands from chemicals and contamination.');

-- --------------------------------------------------------

--
-- Table structure for table `lab_activities`
--

CREATE TABLE `lab_activities` (
  `ActivityID` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `type` enum('Individual','Group') NOT NULL DEFAULT 'Individual',
  `submission_mode` enum('File','Builder') NOT NULL DEFAULT 'File',
  `grouping_mode` enum('None','Manual','Auto','Student') NOT NULL DEFAULT 'None',
  `group_limit` int(11) DEFAULT NULL COMMENT 'Max students per group',
  `Deadline` datetime NOT NULL,
  `Manual_URL` varchar(255) DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_activities`
--

INSERT INTO `lab_activities` (`ActivityID`, `Title`, `Description`, `type`, `submission_mode`, `grouping_mode`, `group_limit`, `Deadline`, `Manual_URL`, `CreatedAt`) VALUES
(1, 'Test after cleaning', 'This is a test', 'Individual', 'File', 'None', NULL, '2025-12-28 19:46:00', 'uploads/manuals/1766911631_Final_Project_in_MAD_121_ACT_AD.pdf', '2025-12-28 08:47:11'),
(2, 'Test after clean with act', 'Act', 'Individual', 'File', 'None', NULL, '2026-01-28 19:00:00', 'uploads/manuals/1766915970_Final_Project_in_MAD_121_ACT_AD.pdf', '2025-12-28 09:59:30'),
(3, 'testing of post', 'test', 'Individual', 'File', 'None', NULL, '2025-12-29 19:18:00', 'uploads/manuals/1766920704_Final_Project_in_MAD_121_ACT_AD.pdf', '2025-12-28 11:18:24'),
(4, 'Testing D2', 'test', 'Individual', 'File', 'None', NULL, '2025-12-30 08:18:00', 'uploads/manuals/1766967533_GROUP_5.pdf', '2025-12-29 00:18:53'),
(5, 'test again d2', 'tasasdawadsa', 'Individual', 'File', 'None', NULL, '2025-12-30 08:51:00', 'uploads/manuals/1766969519_GROUP_5.pdf', '2025-12-29 00:51:59'),
(6, 'this is test d2', 'taawadwasdacasa', 'Individual', 'File', 'None', NULL, '2025-12-30 09:24:00', 'uploads/manuals/1766971499_Final_Project_in_MAD_121_ACT_AD.pdf', '2025-12-29 01:24:59'),
(7, 'test for optimization', 'testing', 'Individual', 'File', 'None', NULL, '2025-12-28 10:34:00', 'uploads/manuals/1766975695_GROUP_5.pdf', '2025-12-29 02:34:55'),
(8, 'dadsad', 'awdada', 'Individual', 'File', 'None', NULL, '2026-01-09 11:59:00', 'uploads/manuals/1767326367_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-02 03:59:27'),
(9, 'testing lang po', 'dasdawawda', 'Individual', 'File', 'None', NULL, '2026-01-13 09:37:00', 'uploads/manuals/1767404271_Kotlin_Calculator_Detailed_Documentation.pdf', '2026-01-03 01:37:51'),
(10, 'testing dual act', 'sadawadasa', 'Individual', 'File', 'None', NULL, '2026-01-21 09:40:00', 'uploads/manuals/1767404445_GROUP_5.pdf', '2026-01-03 01:40:45'),
(11, 'Test begore jsisbsk', 'Hshsisbsns', 'Individual', 'File', 'None', NULL, '2026-03-03 10:46:00', 'uploads/manuals/1767408397_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-03 02:46:37'),
(12, 'testing', 'adewadwasda', 'Group', 'Builder', 'Auto', 2, '2026-01-07 15:17:00', NULL, '2026-01-04 07:17:53'),
(13, 'testing 2', 'sdadweaqsda', 'Group', 'Builder', 'Manual', 2, '2026-01-06 15:21:00', 'uploads/manuals/1767511307_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 07:21:47'),
(14, 'testing 3', 'wedadawqda', 'Group', 'Builder', 'Auto', 2, '2026-01-05 15:27:00', 'uploads/manuals/1767511683_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 07:28:03'),
(15, 'testing 4', 'wedadawqda', 'Group', 'Builder', 'Student', 2, '2026-01-05 15:27:00', 'uploads/manuals/1767511874_MobileAppDevelopmentProjectGuidelines.pdf', '2026-01-04 07:31:14'),
(16, 'testing 5', 'test', 'Group', 'Builder', 'Auto', 10, '2026-01-06 15:57:00', 'uploads/manuals/1767513438_GROUP_5.pdf', '2026-01-04 07:57:18'),
(17, 'testing 6', 'test', 'Group', 'Builder', 'Auto', 2, '2026-01-15 16:00:00', 'uploads/manuals/1767513660_1.pdf__2_.pdf', '2026-01-04 08:01:00'),
(18, 'testing 7', 'test', 'Group', 'Builder', 'Auto', 2, '2026-01-15 16:00:00', 'uploads/manuals/1767513803_1.pdf__2_.pdf', '2026-01-04 08:03:23'),
(19, 'testing 8', 'kjdapojdjpojS', 'Group', 'Builder', 'Auto', 3, '2026-01-06 16:07:00', 'uploads/manuals/1767514102_MobileAppDevelopmentProjectGuidelines.pdf', '2026-01-04 08:08:22'),
(20, 'testing 9', 'lkdfmlokdjf;', 'Group', 'File', 'Manual', 2, '2026-01-06 16:10:00', 'uploads/manuals/1767514258_1.pdf__2_.pdf', '2026-01-04 08:10:58'),
(21, 'testing 10', 'dadawklndalksn', 'Group', 'Builder', 'Manual', 2, '2026-01-05 16:18:00', 'uploads/manuals/1767514765_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 08:19:25'),
(22, 'testing 10', 'dadawklndalksn', 'Group', 'Builder', 'Manual', 2, '2026-01-05 16:18:00', 'uploads/manuals/1767514767_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 08:19:27'),
(23, 'testing 10', 'dadawklndalksn', 'Group', 'Builder', 'Manual', 2, '2026-01-05 16:18:00', 'uploads/manuals/1767514768_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 08:19:28'),
(24, 'testing 10', 'dadawklndalksn', 'Group', 'Builder', 'Manual', 2, '2026-01-05 16:18:00', 'uploads/manuals/1767514768_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 08:19:28'),
(25, 'testing 10', 'dadawklndalksn', 'Group', 'Builder', 'Manual', 2, '2026-01-05 16:18:00', 'uploads/manuals/1767514768_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 08:19:28'),
(26, 'testing 10', 'dadawklndalksn', 'Group', 'Builder', 'Manual', 2, '2026-01-05 16:18:00', 'uploads/manuals/1767514768_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 08:19:28'),
(27, 'testing 10', 'dadawklndalksn', 'Group', 'Builder', 'Manual', 2, '2026-01-05 16:18:00', 'uploads/manuals/1767514768_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 08:19:28'),
(28, 'testing 10', 'dadawklndalksn', 'Group', 'Builder', 'Manual', 2, '2026-01-05 16:18:00', 'uploads/manuals/1767514770_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 08:19:30'),
(29, 'test 11', 'dasdalksmdnlak', 'Group', 'Builder', 'Manual', 2, '2026-01-06 16:27:00', 'uploads/manuals/1767515310_GROUP_5.pdf', '2026-01-04 08:28:30'),
(30, 'test 11', 'dasdalksmdnlak', 'Group', 'Builder', 'Manual', 2, '2026-01-06 16:27:00', 'uploads/manuals/1767515315_GROUP_5.pdf', '2026-01-04 08:28:35'),
(31, 'test 11', 'dasdalksmdnlak', 'Group', 'Builder', 'Manual', 2, '2026-01-06 16:27:00', 'uploads/manuals/1767515479_GROUP_5.pdf', '2026-01-04 08:31:19'),
(32, 'test 11', 'dasdalksmdnlak', 'Group', 'Builder', 'Manual', 2, '2026-01-06 16:27:00', 'uploads/manuals/1767515661_GROUP_5.pdf', '2026-01-04 08:34:21'),
(33, 'test 12', 'test ;lamd', 'Group', 'Builder', 'Manual', 2, '2026-01-13 16:34:00', 'uploads/manuals/1767515751_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 08:35:51'),
(34, 'testing 13', 'a;lmsda\'p;lmd\'as', 'Group', 'Builder', 'Manual', 3, '2026-01-22 16:47:00', 'uploads/manuals/1767516543_GROUP_5.pdf', '2026-01-04 08:49:03'),
(35, 'testing 3types 1', 'sdadadlsdmal;sk', 'Group', 'File', 'Auto', 4, '2026-01-06 18:13:00', 'uploads/manuals/1767521630_MobileAppDevelopmentProjectGuidelines.pdf', '2026-01-04 10:13:50'),
(36, 'testing 3 types 1', 'd;alj\"S:Aasda;lsd;apls', 'Group', 'Builder', 'Auto', 3, '2026-01-07 18:17:00', 'uploads/manuals/1767521885_MobileAppDevelopmentProjectGuidelines.pdf', '2026-01-04 10:18:05'),
(37, 'testing 3 types 2', 'd;alj\"S:Aasda;lsd;apls', 'Group', 'Builder', 'Student', 3, '2026-01-07 18:17:00', 'uploads/manuals/1767521975_MobileAppDevelopmentProjectGuidelines.pdf', '2026-01-04 10:19:35'),
(38, 'testing 3 types 3', 'dasdasda', 'Group', 'Builder', 'Manual', 3, '2026-01-21 18:38:00', 'uploads/manuals/1767523323_1.pdf__2_.pdf', '2026-01-04 10:42:03'),
(39, 'testing 3 types 3.1', 'dasdasda', 'Group', 'Builder', 'Manual', 3, '2026-01-12 18:45:00', 'uploads/manuals/1767523619_MobileAppDevelopmentProjectGuidelines.pdf', '2026-01-04 10:46:59'),
(40, 'Identifying Laboratory Tools', 'Carefully observe each laboratory tool provided. Identify the correct name of the tool and briefly describe its main use in the laboratory. Write your answers clearly and concisely. Make sure that each tool is matched with the correct function.', 'Group', 'Builder', 'Auto', 2, '2026-01-06 06:34:00', 'uploads/manuals/1767566325_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-04 22:38:45'),
(41, 'Measuring Liquid Volume', 'Instructions\r\n\r\nPour a small amount of water into the beaker.\r\n\r\nCarefully transfer the water from the beaker into the graduated cylinder.\r\n\r\nPlace the graduated cylinder on a flat surface.\r\n\r\nUse the ruler to ensure your eye level is even with the liquid surface.\r\n\r\nRead and record the volume of water at the bottom of the meniscus.\r\n\r\nIf needed, use the dropper to add or remove small amounts of water and recheck the measurement.', 'Group', 'Builder', 'Auto', 3, '2026-01-30 23:02:00', 'uploads/manuals/1767625417_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-05 15:03:37'),
(42, 'Observing Changes in Temperature', 'Instructions\r\n\r\nPour warm water into the beaker.\r\n\r\nMeasure and record the initial temperature using the thermometer.\r\n\r\nSlowly add cold water to the beaker.\r\n\r\nStir the mixture gently using the stirring rod.\r\n\r\nMeasure and record the final temperature of the mixture.\r\n\r\nObserve and note the change in temperature.', 'Group', 'Builder', 'Auto', 3, '2026-01-14 08:09:00', 'uploads/manuals/1767658243_GROUP_5.pdf', '2026-01-06 00:10:43'),
(43, 'Examining the Density of Objects', 'Fill the beaker with enough water to allow objects to be fully submerged.\r\n\r\nGently drop the small stone into the water and observe whether it sinks or floats. Record the result.\r\n\r\nPlace the plastic bottle cap onto the surface of the water and observe its behavior. Record the result.\r\n\r\nDrop the metal paperclip into the beaker and note if it sinks or floats. Record the result.\r\n\r\nCompare your observations and determine which objects have higher or lower density relative to water.', 'Group', 'File', 'Student', 3, '2026-01-08 08:58:00', 'uploads/manuals/1767747854_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation___Google_Docs.pdf', '2026-01-07 01:04:14'),
(44, 'testing of the new request', 'alklnNAonnknaaodao;ksndalksnao;icnalkcnoiajfkncalkncal;ksjiojekncalksnlcaknnasclacn;snkda;', 'Group', 'Builder', 'Auto', 3, '2026-01-16 18:56:00', 'uploads/manuals/1767783423_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation___Google_Docs.pdf', '2026-01-07 10:57:03'),
(45, 'testing of the new request', 'alklnNAonnknaaodao;ksndalksnao;icnalkcnoiajfkncalkncal;ksjiojekncalksnlcaknnasclacn;snkda;', 'Group', 'Builder', 'Auto', 3, '2026-01-16 18:56:00', 'uploads/manuals/1767783426_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation___Google_Docs.pdf', '2026-01-07 10:57:06'),
(46, 'testing of the new request', 'alklnNAonnknaaodao;ksndalksnao;icnalkcnoiajfkncalkncal;ksjiojekncalksnlcaknnasclacn;snkda;', 'Group', 'Builder', 'Manual', 3, '2026-01-16 18:56:00', 'uploads/manuals/1767783474_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation___Google_Docs.pdf', '2026-01-07 10:57:54'),
(47, 'testing of the new request', 'alklnNAonnknaaodao;ksndalksnao;icnalkcnoiajfkncalkncal;ksjiojekncalksnlcaknnasclacn;snkda;', 'Group', 'Builder', 'Manual', 3, '2026-01-16 18:56:00', 'uploads/manuals/1767783477_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation___Google_Docs.pdf', '2026-01-07 10:57:57'),
(48, 'testing of the new request', 'alklnNAonnknaaodao;ksndalksnao;icnalkcnoiajfkncalkncal;ksjiojekncalksnlcaknnasclacn;snkda;', 'Group', 'Builder', 'Manual', 3, '2026-01-16 18:56:00', 'uploads/manuals/1767783715_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation___Google_Docs.pdf', '2026-01-07 11:01:55'),
(49, 'testing of the new request', 'alklnNAonnknaaodao;ksndalksnao;icnalkcnoiajfkncalkncal;ksjiojekncalksnlcaknnasclacn;snkda;', 'Group', 'Builder', 'Auto', 3, '2026-01-16 18:56:00', 'uploads/manuals/1767787159_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation___Google_Docs.pdf', '2026-01-07 11:59:19'),
(50, 'testing of the new request', 'alklnNAonnknaaodao;ksndalksnao;icnalkcnoiajfkncalkncal;ksjiojekncalksnlcaknnasclacn;snkda;', 'Group', 'Builder', 'Auto', 3, '2026-01-16 18:56:00', 'uploads/manuals/1767787167_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation___Google_Docs.pdf', '2026-01-07 11:59:27'),
(51, 'testing of the new request', 'alklnNAonnknaaodao;ksndalksnao;icnalkcnoiajfkncalkncal;ksjiojekncalksnlcaknnasclacn;snkda;', 'Group', 'Builder', 'Auto', 3, '2026-01-16 18:56:00', 'uploads/manuals/1767787333_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation___Google_Docs.pdf', '2026-01-07 12:02:13'),
(52, 'individual test', ';lsdaljdaodjlasldalskdalmcalmclajaowjalscma;maspocasasas;mlda;l', 'Individual', 'File', 'Auto', 4, '2026-01-09 10:10:00', 'uploads/manuals/1767838287_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation.pdf', '2026-01-08 02:11:27'),
(53, 'individual with smart system', 'dalkndlandalcakclwjalsmdlacslamcalscmaljfpoalsdnalcl,calascmalmcaojfamclscal', 'Individual', 'Builder', 'Auto', 4, '2026-01-15 10:12:00', 'uploads/manuals/1767838374_TECHNICAL_PREVIEW__SNHS_Laboratory___Academic_Suite___Google_Docs.pdf', '2026-01-08 02:12:54'),
(54, 'Group test student\'s pick', 'lasdlanalcalms\'ascalkcalmsca\'p', 'Group', 'Builder', 'Student', 3, '2026-01-09 10:17:00', 'uploads/manuals/1767838689_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation.pdf', '2026-01-08 02:18:09'),
(55, 'Group test teacher\'s pick', ';al;ms\';lam\'lmalsm\'amsc\'almcalmcpo\'ajlflna\'j\'poass', 'Group', 'File', 'Manual', 3, '2026-01-14 10:18:00', 'uploads/manuals/1767838770_Proposal_for_Digital_Laboratory_Transformation___Pilot_Testing_Invitation___Google_Docs.pdf', '2026-01-08 02:19:30'),
(56, 'Group test Smart system', 'kknflakn;lakcn;skcnao;iscakcna;lkscnal;kcna;', 'Group', 'Builder', 'Auto', 3, '2026-01-27 10:20:00', 'uploads/manuals/1767838837_TECHNICAL_PREVIEW__SNHS_Laboratory___Academic_Suite___Google_Docs.pdf', '2026-01-08 02:20:37'),
(57, 'teacher\'s pick', 'alksnacnalkscnalksncalkkncalknscnkalnscl/as/la c/alc/almscl/a', 'Group', 'Builder', 'Manual', 3, '2026-01-09 10:33:00', 'uploads/manuals/1767839676_Observing_Changes_in_Temperature_Final_Report.pdf', '2026-01-08 02:34:36'),
(58, 'Smart test', 'das alcmsalcanscalkscalscasmcalsmc\'almca\';lmcpaoslaa', 'Group', 'Builder', 'Auto', 3, '2026-01-16 10:45:00', 'uploads/manuals/1767840381_Final_Project_in_MAD_121_ACT_AD.pdf', '2026-01-08 02:46:21');

-- --------------------------------------------------------

--
-- Table structure for table `lab_submissions`
--

CREATE TABLE `lab_submissions` (
  `SubmissionID` int(11) NOT NULL,
  `ActivityID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `GroupID` int(11) DEFAULT NULL,
  `Report_URL` varchar(255) DEFAULT NULL,
  `Grade` varchar(10) DEFAULT NULL,
  `Feedback` text DEFAULT NULL,
  `Status` enum('Pending','Submitted','Graded','Returned') NOT NULL DEFAULT 'Pending',
  `Submitted_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `Is_Late` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_submissions`
--

INSERT INTO `lab_submissions` (`SubmissionID`, `ActivityID`, `StudentID`, `GroupID`, `Report_URL`, `Grade`, `Feedback`, `Status`, `Submitted_At`, `Is_Late`) VALUES
(1, 2, 12, NULL, 'uploads/submissions/REPORT_ACT2_STU12_3df1165a.pdf', '10', 'failed', 'Graded', '2025-12-28 10:43:22', 0),
(3, 3, 11, NULL, 'uploads/submissions/REPORT_ACT3_STU11_087e1c11.pdf', NULL, NULL, 'Submitted', '2025-12-28 11:26:53', 0),
(4, 5, 11, NULL, 'uploads/submissions/REPORT_ACT5_STU11_271a9aba.png', NULL, NULL, 'Submitted', '2025-12-29 01:22:33', 0),
(5, 6, 11, NULL, 'uploads/submissions/REPORT_ACT6_STU11_c8a9ec1f.pdf', '92', 'passed', 'Graded', '2025-12-29 01:27:19', 0),
(7, 11, 11, NULL, 'assets/uploads/reports/Report_11_11_1767409218.pdf', '90', 'passed', 'Graded', '2026-01-03 03:00:18', 0),
(8, 3, 16, NULL, 'assets/uploads/reports/Report_3_16_1767689266.pdf', '75', 'late', 'Graded', '2026-01-06 08:47:46', 0),
(9, 42, 14, 50, 'Digital-Workspace', '75', 'done', 'Graded', '2026-01-06 09:12:30', 0),
(10, 42, 11, 49, 'Digital-Workspace', '90', 'good', 'Graded', '2026-01-06 09:13:16', 0),
(11, 42, 16, 51, 'Digital-Workspace', NULL, 'need to revise more', 'Returned', '2026-01-07 00:26:13', 0);

-- --------------------------------------------------------

--
-- Table structure for table `lookup_masterlist`
--

CREATE TABLE `lookup_masterlist` (
  `MasterID` int(11) NOT NULL,
  `ID_Number` varchar(50) NOT NULL,
  `Full_Name` varchar(100) NOT NULL,
  `Official_Email` varchar(100) DEFAULT NULL,
  `Role` enum('Teacher','Student','Admin') NOT NULL,
  `Date_Added` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lookup_masterlist`
--

INSERT INTO `lookup_masterlist` (`MasterID`, `ID_Number`, `Full_Name`, `Official_Email`, `Role`, `Date_Added`) VALUES
(1, '2025001', 'John Doe', 'john.doe@school.edu', 'Student', '2025-12-28 08:45:26'),
(2, '2025002', 'Jane Smith', 'jane.smith@school.edu', 'Student', '2025-12-28 08:45:26'),
(3, '1001', 'Dr. Robert Brown', 'r.brown@school.edu', 'Teacher', '2025-12-28 08:45:26'),
(4, '1002', 'Prof. Sarah Miller', 's.miller@school.edu', 'Teacher', '2025-12-28 08:45:26'),
(5, '1', 'Admin User', 'admin@school.edu', 'Admin', '2025-12-28 08:45:26'),
(12, '2020403655', 'Mark John Ando', 'andomark922@gmail.com', 'Student', '2025-12-28 08:45:26'),
(13, '202305633', 'Juan DelaPena', 'markando833@gmail.com', 'Student', '2025-12-28 08:45:26'),
(14, '123456789', 'Jomar Jun', 'ae202403655@wmsu.edu.ph', 'Student', '2026-01-03 05:05:47'),
(15, '090507', 'Kim Solis', 'andomark922@gmail.com', 'Student', '2026-01-03 05:19:21'),
(16, '5050', 'Thea Asoy', 'andomark922@gmail.com', 'Student', '2026-01-04 07:39:44'),
(17, '4040', 'Myk Gelaon', 'andomark922@gmail.com', 'Student', '2026-01-04 07:40:03'),
(18, '1010', 'Awis Barcelona', 'andomark922@gmail.com', 'Student', '2026-01-04 07:52:58'),
(19, '2020', 'Sheena Rivero', 'andomark922@gmail.com', 'Student', '2026-01-04 07:53:35'),
(20, '6060', 'Jamal Muktadil', 'andomark922@gmail.com', 'Student', '2026-01-04 10:15:29'),
(21, '9090', 'Kisha Tracy', 'andomark922@gmail.com', 'Student', '2026-01-08 02:03:17');

-- --------------------------------------------------------

--
-- Table structure for table `report_sections`
--

CREATE TABLE `report_sections` (
  `SectionID` int(11) NOT NULL,
  `ActivityID` int(11) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `Title` varchar(100) NOT NULL,
  `Content` longtext DEFAULT NULL,
  `Draft_Content` longtext DEFAULT NULL,
  `Locked_By` int(11) DEFAULT NULL,
  `Locked_At` datetime DEFAULT NULL,
  `Last_Heartbeat` datetime DEFAULT NULL,
  `Last_Updated_By` int(11) DEFAULT NULL,
  `Status` enum('Pending','In Progress','Completed','Needs Revision') DEFAULT 'Pending',
  `Open_Comments_Count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `report_sections`
--

INSERT INTO `report_sections` (`SectionID`, `ActivityID`, `GroupID`, `Title`, `Content`, `Draft_Content`, `Locked_By`, `Locked_At`, `Last_Heartbeat`, `Last_Updated_By`, `Status`, `Open_Comments_Count`) VALUES
(1, 39, 39, 'Introduction', '<p>Laboratory tools are essential in conducting scientific experiments safely and accurately. Each tool has a specific purpose that helps scientists observe, measure, or handle materials properly. This activity aims to familiarize students with common laboratory tools and their basic functions.<span class=\"ql-cursor\">﻿﻿﻿This s﻿﻿sadaskd﻿﻿</span></p>', NULL, NULL, NULL, '2026-01-05 19:38:35', 12, 'Completed', 0),
(2, 39, 39, 'Methodology', '<p>Several common laboratory tools were observed. Each tool was identified by name, and its primary use in the laboratory was noted based on prior knowledge and observation.</p>', NULL, NULL, NULL, '2026-01-05 19:02:03', 12, 'Completed', 0),
(3, 39, 39, 'Data & Results', '<p>The laboratory tools identified included a beaker, test tube, graduated cylinder, thermometer, and microscope. The beaker was identified as a container used for holding and mixing liquids. The test tube was recognized as a tool for holding small amounts of substances. The graduated cylinder was identified as a tool used for measuring liquid volume accurately. The thermometer was identified as a device used to measure temperature, while the microscope was identified as an instrument used to observe small objects not visible to the naked eye.</p>', NULL, NULL, NULL, '2026-01-05 19:26:33', 12, 'Completed', 0),
(4, 39, 39, 'Conclusion', '<p><span style=\"color: rgb(51, 65, 85);\">The activity successfully demonstrated the importance of correctly identifying laboratory tools and understanding their functions. Proper knowledge of these tools helps ensure accuracy and safety during laboratory work and prepares students for more advanced scientific experiments.</span></p><p><br></p><p><img src=\"data:image/jpeg;base64,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\"></p><p><br></p>', NULL, NULL, NULL, NULL, 12, 'Completed', 1),
(5, 42, 49, 'Introduction', '<p>Temperature changes occur when substances with different temperatures are combined. This activity demonstrates how heat is transferred when warm and cold water are mixed.</p>', NULL, NULL, NULL, '2026-01-06 16:04:59', 12, 'Completed', 0),
(6, 42, 49, 'Methodology', '<p>Temperature changes occur when substances with different temperatures are combined. This activity demonstrates how heat is transferred when warm and cold water are mixed. </p>', NULL, NULL, NULL, '2026-01-06 16:16:03', 12, 'Completed', 0),
(7, 42, 49, 'Results & Discussion', '<p>After mixing the warm and cold water, the temperature of the mixture was observed to be between the initial temperatures of the two liquids.</p>', NULL, NULL, NULL, NULL, 19, 'Completed', 0),
(8, 42, 49, 'Conclusion', '<p><strong>The activity showed that mixing warm</strong> and cold water <em>results in</em> a temperature change due to heat transfer. This confirms that heat moves from <u>warmer substances to cooler ones.</u></p><p><img src=\"data:image/jpeg;base64,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\" style=\"display: inline; margin: 0px 1em 1em 0px; float: left;\"></p><p><br></p><p><br></p><p><br></p><p><br></p><p><br></p><p><br></p><p><br></p><p><br></p><p><br></p>', NULL, NULL, NULL, '2026-01-06 16:06:20', 19, 'Completed', 0),
(9, 14, 2, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(10, 14, 2, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(11, 14, 2, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(12, 14, 2, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(13, 15, 3, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(14, 15, 3, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(15, 15, 3, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(16, 15, 3, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(17, 15, 4, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(18, 15, 4, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(19, 15, 4, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(20, 15, 4, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(21, 15, 5, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(22, 15, 5, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(23, 15, 5, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(24, 15, 5, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(25, 16, 6, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(26, 16, 6, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(27, 16, 6, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(28, 16, 6, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(29, 17, 7, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(30, 17, 7, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(31, 17, 7, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(32, 17, 7, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(33, 17, 8, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(34, 17, 8, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(35, 17, 8, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(36, 17, 8, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(37, 17, 9, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(38, 17, 9, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(39, 17, 9, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(40, 17, 9, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(41, 18, 10, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(42, 18, 10, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(43, 18, 10, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(44, 18, 10, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(45, 18, 11, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(46, 18, 11, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(47, 18, 11, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(48, 18, 11, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(49, 18, 12, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(50, 18, 12, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(51, 18, 12, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(52, 18, 12, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(53, 19, 13, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(54, 19, 13, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(55, 19, 13, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(56, 19, 13, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(57, 19, 14, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(58, 19, 14, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(59, 19, 14, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(60, 19, 14, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(61, 29, 15, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(62, 29, 15, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(63, 29, 15, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(64, 29, 15, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(65, 30, 16, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(66, 30, 16, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(67, 30, 16, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(68, 30, 16, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(69, 31, 17, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(70, 31, 17, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(71, 31, 17, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(72, 31, 17, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(73, 32, 18, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(74, 32, 18, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(75, 32, 18, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(76, 32, 18, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(77, 32, 19, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(78, 32, 19, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(79, 32, 19, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(80, 32, 19, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(81, 32, 20, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(82, 32, 20, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(83, 32, 20, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(84, 32, 20, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(85, 33, 21, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(86, 33, 21, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(87, 33, 21, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(88, 33, 21, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(89, 33, 22, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(90, 33, 22, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(91, 33, 22, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(92, 33, 22, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(93, 34, 23, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(94, 34, 23, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(95, 34, 23, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(96, 34, 23, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(97, 34, 24, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(98, 34, 24, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(99, 34, 24, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(100, 34, 24, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(101, 35, 25, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(102, 35, 25, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(103, 35, 25, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(104, 35, 25, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(105, 35, 26, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(106, 35, 26, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(107, 35, 26, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(108, 35, 26, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(109, 35, 27, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(110, 35, 27, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(111, 35, 27, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(112, 35, 27, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(113, 36, 28, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(114, 36, 28, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(115, 36, 28, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(116, 36, 28, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(117, 36, 29, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(118, 36, 29, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(119, 36, 29, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(120, 36, 29, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(121, 36, 30, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(122, 36, 30, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(123, 36, 30, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(124, 36, 30, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(125, 37, 31, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(126, 37, 31, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(127, 37, 31, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(128, 37, 31, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(129, 37, 32, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(130, 37, 32, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(131, 37, 32, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(132, 37, 32, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(133, 37, 33, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(134, 37, 33, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(135, 37, 33, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(136, 37, 33, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(137, 38, 34, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(138, 38, 34, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(139, 38, 34, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(140, 38, 34, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(141, 38, 35, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(142, 38, 35, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(143, 38, 35, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(144, 38, 35, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(145, 38, 36, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(146, 38, 36, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(147, 38, 36, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(148, 38, 36, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(149, 39, 37, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(150, 39, 37, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(151, 39, 37, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(152, 39, 37, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(153, 39, 38, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(154, 39, 38, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(155, 39, 38, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(156, 39, 38, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(157, 40, 40, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(158, 40, 40, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(159, 40, 40, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(160, 40, 40, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(161, 40, 41, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(162, 40, 41, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(163, 40, 41, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(164, 40, 41, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(165, 40, 42, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(166, 40, 42, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(167, 40, 42, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(168, 40, 42, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(169, 40, 43, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(170, 40, 43, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(171, 40, 43, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(172, 40, 43, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(173, 25, 44, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(174, 25, 44, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(175, 25, 44, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(176, 25, 44, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(177, 30, 45, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(178, 30, 45, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(179, 30, 45, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(180, 30, 45, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(181, 41, 46, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(182, 41, 46, 'Methodology', '<p>sklk</p>', NULL, NULL, NULL, NULL, 12, 'Completed', 0),
(183, 41, 46, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(184, 41, 46, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(185, 41, 47, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(186, 41, 47, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(187, 41, 47, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(188, 41, 47, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(189, 41, 48, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(190, 41, 48, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(191, 41, 48, 'Results & Discussion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(192, 41, 48, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'Pending', 0),
(193, 42, 50, 'Introduction', '<p>Temperature changes occur when substances with different temperatures are combined. This activity demonstrates how heat is transferred when warm and cold water are mixed.</p>', NULL, NULL, NULL, NULL, 14, 'Completed', 0),
(194, 42, 50, 'Methodology', '<p>Warm water was placed in a beaker and its initial temperature was measured using a thermometer. Cold water was then added and the mixture was stirred. The final temperature was recorded.</p>', NULL, NULL, NULL, NULL, 14, 'Completed', 0),
(195, 42, 50, 'Results & Discussion', '<p>After mixing the warm and cold water, the temperature of the mixture was observed to be between the initial temperatures of the two liquids.</p>', NULL, NULL, NULL, NULL, 14, 'Completed', 0),
(196, 42, 50, 'Conclusion', '<p>The activity showed that mixing warm and cold water results in a temperature change due to heat transfer. This confirms that heat moves from warmer substances to cooler ones.</p>', NULL, NULL, NULL, NULL, 14, 'Completed', 0),
(197, 42, 51, 'Introduction', '<p>Temperature changes occur when substances with different temperatures are combined. This activity demonstrates how heat is transferred when warm and cold water are mixed.</p>', '<p>Temperature changes occur when substances with different temperatures are combined. This activity demonstrates how heat is transferred when warm and cold water are mixed.</p>', 16, '2026-01-07 08:28:23', '2026-01-07 08:28:23', 16, 'In Progress', 0),
(198, 42, 51, 'Methodology', '<p>The methodology for this activity was designed to provide a structured, systematic, and observable procedure for examining how temperature changes when warm and cold water are mixed together. This section outlines the materials used, the preparation steps taken before the experiment, the detailed procedure followed during the data collection process, and the measures implemented to ensure accuracy, consistency, and reliability of the results. The intention of this methodology is to allow the experiment to be replicated easily by other students or researchers and to maintain clarity in describing each step involved in the investigation.</p><p>To begin the activity, all necessary apparatus were prepared and arranged in a clean and organized workspace. The materials used for this experiment included one laboratory thermometer, one standard beaker, a source of warm water, a source of cold water, and a stirring rod. Each apparatus was inspected to ensure it was clean, dry, and functioning properly. The thermometer, in particular, was checked for cracks or defects that could affect temperature readings. The beaker was chosen to be transparent and large enough to hold the mixture of warm and cold water. The stirring rod was selected to allow gentle yet effective mixing without causing splashing, which could alter the amount of liquid in the container.</p><p>Before beginning the main procedure, a preliminary setup was conducted to ensure that the temperature sources were appropriate for comparison. Warm water was prepared at a safe and moderate temperature, avoiding extremes that could damage the thermometer or cause safety concerns. Similarly, cold water was obtained either from a refrigerated container or through the addition of ice, depending on availability, but always ensuring that the ice was removed before measurement so that the volume of water remained consistent. Both water samples were kept in separate containers and were not mixed until actual measurement and observation began.</p><p>The procedure started by pouring a measured amount of warm water into the beaker. Care was taken to avoid spilling and to ensure that the beaker remained stable on a flat surface. Once the warm water was inside the beaker, the thermometer was carefully inserted into the water without touching the bottom or the sides of the beaker, as contact with the glass could interfere with the temperature reading. The thermometer was allowed to sit in the water until a stable temperature reading was achieved. This initial reading was recorded as the baseline temperature of the warm water.</p><p>After recording the warm water temperature, attention shifted to the cold water. A predetermined amount of cold water was prepared for addition to the beaker. This amount was controlled to ensure that the experiment remained consistent and comparable if repeated. The cold water was then slowly poured into the beaker containing the warm water. The pouring process was done gently to minimize splashing and prevent accidental loss of liquid, which could influence the accuracy of the final temperature measurement.</p><p>Immediately after adding the cold water, the stirring rod was used to mix the water gently. Stirring was essential to ensure that the warm and cold water blended thoroughly, creating a uniform temperature throughout the beaker. The stirring motion was slow and continuous for several seconds, allowing the heat transfer to occur naturally and evenly. Once the mixture appeared consistent, the thermometer was again placed in the center of the beaker without touching its surfaces. It was left in place until the temperature stabilized. This reading represented the final temperature of the mixture and was recorded promptly.</p><p>Throughout the procedure, care was taken to control environmental factors that could influence the results. The experiment was performed indoors, away from direct sunlight, electric fans, or heaters that could alter the temperature of the water samples. The entire setup remained undisturbed except for the required stirring to ensure valid measurements.</p><p>Finally, the temperature readings were reviewed. The difference between the initial warm water temperature and the final mixture temperature was noted. The relationship between the cold water and warm water temperatures and the resulting mixture was described based on the observed data.</p><p>This methodology ensures a clear, accurate, and reproducible process for studying temperature changes during the mixing of warm and cold water. It emphasizes organization, precision in measurement, and careful handling of materials, all of which contribute to producing reliable and meaningful results.</p>', NULL, NULL, NULL, '2026-01-06 20:09:22', 16, 'Completed', 0),
(199, 42, 51, 'Results & Discussion', '<p>After mixing the warm and cold water, the temperature of the mixture was observed to be between the initial temperatures of the two liquids.</p>', NULL, NULL, NULL, NULL, 16, 'In Progress', 0),
(200, 42, 51, 'Conclusion', '<p>The activity showed that mixing warm and cold water results in a temperature change due to heat transfer. This confirms that heat moves from warmer substances to cooler ones.</p>', NULL, NULL, NULL, NULL, 16, 'In Progress', 0),
(201, 51, 59, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(202, 51, 59, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(203, 51, 59, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(204, 51, 59, 'Discussion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(205, 51, 59, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(206, 51, 60, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(207, 51, 60, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(208, 51, 60, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(209, 51, 60, 'Discussion', '<p><br></p>', NULL, NULL, NULL, NULL, 12, 'Completed', 0),
(210, 51, 60, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(211, 51, 61, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(212, 51, 61, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(213, 51, 61, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(214, 51, 61, 'Discussion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(215, 51, 61, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(216, 56, 65, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(217, 56, 65, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(218, 56, 65, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(219, 56, 65, 'Discussion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(220, 56, 65, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(221, 56, 66, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(222, 56, 66, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(223, 56, 66, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(224, 56, 66, 'Discussion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(225, 56, 66, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(226, 56, 67, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(227, 56, 67, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(228, 56, 67, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(229, 56, 67, 'Discussion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(230, 56, 67, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(231, 57, 68, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(232, 57, 68, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(233, 57, 68, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(234, 57, 68, 'Discussion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(235, 57, 68, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(236, 57, 69, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(237, 57, 69, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(238, 57, 69, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(239, 57, 69, 'Discussion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(240, 57, 69, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(241, 57, 70, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(242, 57, 70, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(243, 57, 70, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(244, 57, 70, 'Discussion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(245, 57, 70, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(246, 58, 71, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(247, 58, 71, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(248, 58, 71, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(249, 58, 71, 'Discussion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(250, 58, 71, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(251, 58, 72, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(252, 58, 72, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(253, 58, 72, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(254, 58, 72, 'Discussion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(255, 58, 72, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(256, 58, 73, 'Introduction', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(257, 58, 73, 'Methodology', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(258, 58, 73, 'Results', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(259, 58, 73, 'Discussion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0),
(260, 58, 73, 'Conclusion', '', NULL, NULL, NULL, NULL, NULL, 'In Progress', 0);

-- --------------------------------------------------------

--
-- Table structure for table `section_comments`
--

CREATE TABLE `section_comments` (
  `CommentID` int(11) NOT NULL,
  `SectionID` int(11) DEFAULT NULL,
  `MasterID` int(11) DEFAULT NULL,
  `Highlight_Index` int(11) DEFAULT NULL,
  `Highlight_Length` int(11) DEFAULT NULL,
  `Comment_Text` text DEFAULT NULL,
  `Status` enum('Open','Resolved') DEFAULT 'Open',
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section_comments`
--

INSERT INTO `section_comments` (`CommentID`, `SectionID`, `MasterID`, `Highlight_Index`, `Highlight_Length`, `Comment_Text`, `Status`, `CreatedAt`) VALUES
(1, 4, 12, 0, 546, 'all of this is not here sine this is methods', 'Open', '2026-01-05 10:34:04'),
(2, 8, 12, 0, 174, 'do not make it too fancy', 'Open', '2026-01-06 07:32:17'),
(3, 5, 12, 1, 168, 'this is not intro', 'Resolved', '2026-01-06 07:43:23'),
(4, 5, 12, 0, 170, 'wrrong', 'Resolved', '2026-01-06 07:56:55'),
(5, 8, 12, 0, 174, 'not too fancy', 'Open', '2026-01-06 08:05:27'),
(6, 6, 12, 171, 15, 'remove', 'Open', '2026-01-06 08:16:57');

-- --------------------------------------------------------

--
-- Table structure for table `section_history`
--

CREATE TABLE `section_history` (
  `HistoryID` int(11) NOT NULL,
  `SectionID` int(11) NOT NULL,
  `MasterID` int(11) NOT NULL,
  `Characters_Added` int(11) DEFAULT 0,
  `Timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `Content_Snapshot` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section_history`
--

INSERT INTO `section_history` (`HistoryID`, `SectionID`, `MasterID`, `Characters_Added`, `Timestamp`, `Content_Snapshot`) VALUES
(1, 2, 12, 0, '2026-01-05 10:06:04', NULL),
(2, 2, 12, 0, '2026-01-05 10:06:21', NULL),
(3, 1, 19, 0, '2026-01-05 10:09:34', NULL),
(4, 4, 19, 0, '2026-01-05 10:10:25', NULL),
(5, 3, 17, 0, '2026-01-05 10:11:03', NULL),
(6, 3, 12, 0, '2026-01-05 10:11:38', NULL),
(7, 2, 12, 0, '2026-01-05 10:13:03', NULL),
(8, 4, 12, 0, '2026-01-05 10:13:14', NULL),
(9, 1, 12, 0, '2026-01-05 10:21:04', NULL),
(10, 1, 12, 0, '2026-01-05 10:31:51', NULL),
(11, 3, 12, 0, '2026-01-05 10:31:54', NULL),
(12, 1, 12, 0, '2026-01-05 10:33:18', NULL),
(13, 4, 12, 0, '2026-01-05 10:34:09', NULL),
(14, 4, 19, 0, '2026-01-05 10:35:04', NULL),
(15, 4, 19, 0, '2026-01-05 10:35:21', NULL),
(16, 3, 19, 0, '2026-01-05 10:35:29', NULL),
(17, 4, 19, 0, '2026-01-05 10:35:34', NULL),
(18, 3, 19, 0, '2026-01-05 10:35:49', NULL),
(19, 4, 12, 0, '2026-01-05 10:46:10', NULL),
(20, 3, 12, 0, '2026-01-05 11:26:50', NULL),
(21, 3, 12, 0, '2026-01-05 11:26:54', NULL),
(22, 1, 12, 0, '2026-01-05 11:27:00', NULL),
(23, 2, 12, 0, '2026-01-05 11:27:02', NULL),
(24, 1, 12, 0, '2026-01-05 11:39:36', '<p><em>Laboratory tools</em> are essential in conducting scientific experiments safely and accurately. Each tool has a specific purpose that helps scientists observe, measure, or handle materials properly. This activity<strong> aims to familiarize students with common laboratory tools and their basic functions. </strong><span class=\"ql-cursor\">﻿﻿</span></p>'),
(25, 1, 12, 0, '2026-01-05 11:45:32', '<p><em>Laboratory tools</em> are essential in conducting scientific experiments safely and accurately. Each tool has a specific purpose that helps scientists observe, measure, or handle materials properly. This activity<strong> aims to familiarize students with common laboratory tools and their basic functions. </strong><span class=\"ql-cursor\">﻿﻿﻿This s</span></p>'),
(26, 1, 12, 0, '2026-01-05 11:45:51', '<p><em>Laboratory tools</em> are essential in conducting scientific experiments safely and accurately. Each tool has a specific purpose that helps scientists observe, measure, or handle materials properly. This activity<strong> aims to familiarize students with common laboratory tools and their basic functions.</strong><span class=\"ql-cursor\">﻿﻿﻿This s﻿</span></p>'),
(27, 1, 12, 0, '2026-01-05 11:46:09', '<p><span class=\"ql-cursor\">﻿﻿﻿This s﻿﻿sadaskd</span></p>'),
(28, 2, 12, 0, '2026-01-05 11:46:46', '<p><br></p>'),
(29, 2, 12, 0, '2026-01-05 11:47:00', '<p>This is the aldasdalsda</p>'),
(30, 1, 12, 0, '2026-01-05 11:47:38', '<p><span class=\"ql-cursor\">﻿﻿﻿This s﻿﻿sadaskd﻿</span></p>'),
(31, 2, 12, 0, '2026-01-05 11:48:35', '<p>Several common laboratory tools were observed. Each tool was identified by name, and its primary use in the laboratory was noted based on prior knowledge and observation.</p>'),
(32, 1, 12, 0, '2026-01-05 11:48:52', '<p>Laboratory tools are essential in conducting scientific experiments safely and accurately. Each tool has a specific purpose that helps scientists observe, measure, or handle materials properly. This activity aims to familiarize students with common laboratory tools and their basic functions.<span class=\"ql-cursor\">﻿﻿﻿This s﻿﻿sadaskd﻿﻿</span></p>'),
(33, 6, 12, 0, '2026-01-06 07:06:48', '<p><br></p>'),
(34, 8, 12, 0, '2026-01-06 07:07:03', '<p><br></p>'),
(35, 5, 12, 0, '2026-01-06 07:07:08', '<p><br></p>'),
(36, 7, 12, 0, '2026-01-06 07:07:14', '<p><br></p>'),
(37, 8, 20, 0, '2026-01-06 07:10:57', '<p><strong>The activity showed that mixing warm</strong> and cold water <em>results in</em> a temperature change due to heat transfer. This confirms that heat moves from <u>warmer substances to cooler ones.</u></p><p><img src=\"data:image/jpeg;base64,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\" style=\"display: block; margin: auto;\"></p><p><br></p>'),
(38, 5, 19, 0, '2026-01-06 07:11:48', '<p>Temperature changes occur when substances with different temperatures are combined. This activity demonstrates how heat is transferred when warm and cold water are mixed.</p>'),
(39, 7, 19, 0, '2026-01-06 07:12:13', '<p>After mixing the warm and cold water, the temperature of the mixture was observed to be between the initial temperatures of the two liquids.</p>'),
(40, 6, 12, 0, '2026-01-06 07:12:44', '<p>Warm water was placed in a beaker and its initial temperature was measured using a thermometer. Cold water was then added and the mixture was stirred. The final temperature was recorded.</p>'),
(41, 6, 19, 0, '2026-01-06 07:38:58', '<p>Temperature changes occur when substances with different temperatures are combined. This activity demonstrates how heat is transferred when warm and cold water are mixed.</p>'),
(42, 8, 19, 0, '2026-01-06 08:06:26', '<p><strong>The activity showed that mixing warm</strong> and cold water <em>results in</em> a temperature change due to heat transfer. This confirms that heat moves from <u>warmer substances to cooler ones.</u></p><p><img src=\"data:image/jpeg;base64,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\" style=\"display: inline; margin: 0px 1em 1em 0px; float: left;\"></p><p><br></p><p><br></p><p><br></p><p><br></p><p><br></p><p><br></p><p><br></p><p><br></p><p><br></p>'),
(43, 6, 12, 0, '2026-01-06 08:16:46', '<p>Temperature changes occur when substances with different temperatures are combined. This activity demonstrates how heat is transferred when warm and cold water are mixed. This is not it.</p>'),
(44, 6, 12, 0, '2026-01-06 08:17:40', '<p>Temperature changes occur when substances with different temperatures are combined. This activity demonstrates how heat is transferred when warm and cold water are mixed. </p>'),
(45, 197, 16, 0, '2026-01-06 08:43:38', '<p>Temperature changes occur when substances with different temperatures are combined. This activity demonstrates how heat is transferred when warm and cold water are mixed.</p>'),
(46, 198, 16, 0, '2026-01-06 08:43:48', '<p>Warm water was placed in a beaker and its initial temperature was measured using a thermometer. Cold water was then added and the mixture was stirred. The final temperature was recorded.</p>'),
(47, 199, 17, 0, '2026-01-06 08:44:41', '<p>After mixing the warm and cold water, the temperature of the mixture was observed to be between the initial temperatures of the two liquids.</p>'),
(48, 200, 17, 0, '2026-01-06 08:44:51', '<p>The activity showed that mixing warm and cold water results in a temperature change due to heat transfer. This confirms that heat moves from warmer substances to cooler ones.</p>'),
(49, 193, 14, 0, '2026-01-06 08:50:23', '<p>Temperature changes occur when substances with different temperatures are combined. This activity demonstrates how heat is transferred when warm and cold water are mixed.</p>'),
(50, 194, 14, 0, '2026-01-06 08:50:34', '<p>Warm water was placed in a beaker and its initial temperature was measured using a thermometer. Cold water was then added and the mixture was stirred. The final temperature was recorded.</p>'),
(51, 195, 14, 0, '2026-01-06 08:50:45', '<p>After mixing the warm and cold water, the temperature of the mixture was observed to be between the initial temperatures of the two liquids.</p>'),
(52, 196, 14, 0, '2026-01-06 08:50:56', '<p>The activity showed that mixing warm and cold water results in a temperature change due to heat transfer. This confirms that heat moves from warmer substances to cooler ones.</p>'),
(53, 198, 17, 0, '2026-01-07 00:03:39', '<p>The methodology for this activity was designed to provide a structured, systematic, and observable procedure for examining how temperature changes when warm and cold water are mixed together. This section outlines the materials used, the preparation steps taken before the experiment, the detailed procedure followed during the data collection process, and the measures implemented to ensure accuracy, consistency, and reliability of the results. The intention of this methodology is to allow the experiment to be replicated easily by other students or researchers and to maintain clarity in describing each step involved in the investigation.</p><p>To begin the activity, all necessary apparatus were prepared and arranged in a clean and organized workspace. The materials used for this experiment included one laboratory thermometer, one standard beaker, a source of warm water, a source of cold water, and a stirring rod. Each apparatus was inspected to ensure it was clean, dry, and functioning properly. The thermometer, in particular, was checked for cracks or defects that could affect temperature readings. The beaker was chosen to be transparent and large enough to hold the mixture of warm and cold water. The stirring rod was selected to allow gentle yet effective mixing without causing splashing, which could alter the amount of liquid in the container.</p><p>Before beginning the main procedure, a preliminary setup was conducted to ensure that the temperature sources were appropriate for comparison. Warm water was prepared at a safe and moderate temperature, avoiding extremes that could damage the thermometer or cause safety concerns. Similarly, cold water was obtained either from a refrigerated container or through the addition of ice, depending on availability, but always ensuring that the ice was removed before measurement so that the volume of water remained consistent. Both water samples were kept in separate containers and were not mixed until actual measurement and observation began.</p><p>The procedure started by pouring a measured amount of warm water into the beaker. Care was taken to avoid spilling and to ensure that the beaker remained stable on a flat surface. Once the warm water was inside the beaker, the thermometer was carefully inserted into the water without touching the bottom or the sides of the beaker, as contact with the glass could interfere with the temperature reading. The thermometer was allowed to sit in the water until a stable temperature reading was achieved. This initial reading was recorded as the baseline temperature of the warm water.</p><p>After recording the warm water temperature, attention shifted to the cold water. A predetermined amount of cold water was prepared for addition to the beaker. This amount was controlled to ensure that the experiment remained consistent and comparable if repeated. The cold water was then slowly poured into the beaker containing the warm water. The pouring process was done gently to minimize splashing and prevent accidental loss of liquid, which could influence the accuracy of the final temperature measurement.</p><p>Immediately after adding the cold water, the stirring rod was used to mix the water gently. Stirring was essential to ensure that the warm and cold water blended thoroughly, creating a uniform temperature throughout the beaker. The stirring motion was slow and continuous for several seconds, allowing the heat transfer to occur naturally and evenly. Once the mixture appeared consistent, the thermometer was again placed in the center of the beaker without touching its surfaces. It was left in place until the temperature stabilized. This reading represented the final temperature of the mixture and was recorded promptly.</p><p>Throughout the procedure, care was taken to control environmental factors that could influence the results. The experiment was performed indoors, away from direct sunlight, electric fans, or heaters that could alter the temperature of the water samples. The entire setup remained undisturbed except for the required stirring to ensure valid measurements.</p><p>Finally, the temperature readings were reviewed. The difference between the initial warm water temperature and the final mixture temperature was noted. The relationship between the cold water and warm water temperatures and the resulting mixture was described based on the observed data.</p><p>This methodology ensures a clear, accurate, and reproducible process for studying temperature changes during the mixing of warm and cold water. It emphasizes organization, precision in measurement, and careful handling of materials, all of which contribute to producing reliable and meaningful results.</p>'),
(54, 182, 12, 0, '2026-01-07 06:19:25', '<p>sklk</p>'),
(55, 209, 12, 0, '2026-01-07 12:07:29', '<p><br></p>');

-- --------------------------------------------------------

--
-- Table structure for table `student_stats`
--

CREATE TABLE `student_stats` (
  `StatID` int(11) NOT NULL,
  `MasterID` int(11) NOT NULL COMMENT 'Links to lookup_masterlist.MasterID',
  `Total_Points` int(11) DEFAULT 0,
  `Leader_Count` int(11) DEFAULT 0,
  `Avg_Contribution` decimal(5,2) DEFAULT 0.00,
  `Last_Updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UserID` int(11) NOT NULL,
  `MasterID` int(11) NOT NULL,
  `Confirmed_Email` varchar(100) NOT NULL,
  `Password_Hash` varchar(255) NOT NULL,
  `Is_Verified` tinyint(1) DEFAULT 0,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `MasterID`, `Confirmed_Email`, `Password_Hash`, `Is_Verified`, `Created_At`) VALUES
(8, 5, 'sciencelabinventorysystem@gmail.com', '$2y$10$wEiTk3emad0uQw5aCT/3duedmBhg.JeNvS495X.ZXRBZ9BIFbXfZu', 1, '2025-12-28 08:45:26'),
(9, 4, 'ae202403655@wmsu.edu.ph', '$2y$10$CW8VLwFg1ds4g/fjNFI83uuTnNY46ENL/2RgZ4gS7lpcqVDXiuXRO', 1, '2025-12-28 08:45:26'),
(11, 12, 'andomark922@gmail.com', '$2y$10$lqETVl1wWbW1S3MF5ToXte0FkPMDwLsNARACve1ogDwkmAgL6TZWa', 1, '2025-12-28 08:45:26'),
(12, 13, 'markando833@gmail.com', '$2y$10$WkFXCQvb2QeelQ9xT3jh..J2oVDxBVnYmzOqLvOMvWGVRi/iXg/N6', 1, '2025-12-28 08:45:26'),
(14, 14, 'ae202403655@wmsu.edu.ph', '$2y$10$pOIQkpPhoUfo2v0UJy9DrOn.6EL8Q18vZ34D6DyGnISb6Wy1rKYwC', 1, '2026-01-03 05:09:15'),
(15, 15, 'andomark922@gmail.com', '$2y$10$4iAuxwWrGo7lJTFXldZyeeP/MmEGgRTkIOOiQJyPL5sHY5p1Kx1fe', 1, '2026-01-03 05:20:02'),
(16, 16, 'andomark922@gmail.com', '$2y$10$egJe2h0vEkd5tmwFqbm2A.BHSzfE1PmaOH3x2eAcFL1qdXTWJZXuq', 1, '2026-01-04 07:40:37'),
(17, 17, 'andomark922@gmail.com', '$2y$10$1ARuxgYEqCIjmYVf3vRbqOs3ovyv9zUZrXLr.MBqsS.TaJ7UvMgem', 1, '2026-01-04 07:41:25'),
(18, 18, 'andomark922@gmail.com', '$2y$10$lJ0YUYHs2uOUft3z86DD2.DFOHmTOsFqFUOsk.vJutuRFiZKpF4qu', 1, '2026-01-04 07:54:41'),
(19, 19, 'andomark922@gmail.com', '$2y$10$hjNBuOFhYhCGa.OXmKWqgusnGmmnRfWmOztOuCflJ8OvpD.YPNFsC', 1, '2026-01-04 07:56:12'),
(20, 20, 'andomark922@gmail.com', '$2y$10$/SyIWhku10zEHq.x8qdomO6ed4q5pwUHBlUd29/M4FXVdX5U8GNrG', 1, '2026-01-04 10:16:03'),
(21, 21, 'andomark922@gmail.com', '$2y$10$UogL18qnhJy8HDFSzHO7AOOu13rX8uEvOPjIRKKU4wA1zveyYvGrK', 1, '2026-01-08 02:03:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_assignments`
--
ALTER TABLE `activity_assignments`
  ADD PRIMARY KEY (`AssignmentID`),
  ADD KEY `activity_assignments_ibfk_1` (`ActivityID`),
  ADD KEY `activity_assignments_ibfk_2` (`ClassID`);

--
-- Indexes for table `activity_grades`
--
ALTER TABLE `activity_grades`
  ADD PRIMARY KEY (`GradeID`),
  ADD UNIQUE KEY `unique_grade` (`ActivityID`,`StudentID`);

--
-- Indexes for table `activity_groups`
--
ALTER TABLE `activity_groups`
  ADD PRIMARY KEY (`GroupID`),
  ADD KEY `fk_group_activity` (`ActivityID`);

--
-- Indexes for table `activity_requirements`
--
ALTER TABLE `activity_requirements`
  ADD PRIMARY KEY (`RequirementID`),
  ADD KEY `fk_req_activity` (`ActivityID`),
  ADD KEY `fk_req_inventory` (`ItemID`);

--
-- Indexes for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  ADD PRIMARY KEY (`BorrowedItemID`),
  ADD KEY `fk_borrowed_session` (`SessionID`),
  ADD KEY `fk_borrowed_inventory` (`ItemID`),
  ADD KEY `fk_possessor` (`Possessor_MasterID`);

--
-- Indexes for table `borrowing_sessions`
--
ALTER TABLE `borrowing_sessions`
  ADD PRIMARY KEY (`SessionID`),
  ADD KEY `fk_session_activity` (`ActivityID`),
  ADD KEY `fk_session_student` (`StudentID`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`CategoryID`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`ClassID`),
  ADD KEY `classes_ibfk_1` (`TeacherID`);

--
-- Indexes for table `class_enrollment`
--
ALTER TABLE `class_enrollment`
  ADD PRIMARY KEY (`EnrollmentID`),
  ADD KEY `class_enrollment_ibfk_1` (`ClassID`),
  ADD KEY `fk_enrollment_master` (`MasterID`);

--
-- Indexes for table `damaged_returns`
--
ALTER TABLE `damaged_returns`
  ADD PRIMARY KEY (`damage_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `group_logistics`
--
ALTER TABLE `group_logistics`
  ADD PRIMARY KEY (`LogisticsID`),
  ADD KEY `ActivityID` (`ActivityID`),
  ADD KEY `GroupID` (`GroupID`),
  ADD KEY `ItemID` (`ItemID`),
  ADD KEY `AssignedToMasterID` (`AssignedToMasterID`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`MemberID`),
  ADD KEY `fk_member_group` (`GroupID`),
  ADD KEY `fk_member_master` (`MasterID`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`ItemID`),
  ADD KEY `inventory_ibfk_1` (`CategoryID`);

--
-- Indexes for table `lab_activities`
--
ALTER TABLE `lab_activities`
  ADD PRIMARY KEY (`ActivityID`);

--
-- Indexes for table `lab_submissions`
--
ALTER TABLE `lab_submissions`
  ADD PRIMARY KEY (`SubmissionID`),
  ADD UNIQUE KEY `unique_student_activity` (`ActivityID`,`StudentID`),
  ADD KEY `fk_sub_student` (`StudentID`),
  ADD KEY `fk_sub_group` (`GroupID`);

--
-- Indexes for table `lookup_masterlist`
--
ALTER TABLE `lookup_masterlist`
  ADD PRIMARY KEY (`MasterID`),
  ADD UNIQUE KEY `ID_Number` (`ID_Number`);

--
-- Indexes for table `report_sections`
--
ALTER TABLE `report_sections`
  ADD PRIMARY KEY (`SectionID`),
  ADD KEY `ActivityID` (`ActivityID`),
  ADD KEY `GroupID` (`GroupID`),
  ADD KEY `fk_section_locker` (`Locked_By`);

--
-- Indexes for table `section_comments`
--
ALTER TABLE `section_comments`
  ADD PRIMARY KEY (`CommentID`),
  ADD KEY `SectionID` (`SectionID`);

--
-- Indexes for table `section_history`
--
ALTER TABLE `section_history`
  ADD PRIMARY KEY (`HistoryID`),
  ADD KEY `SectionID` (`SectionID`);

--
-- Indexes for table `student_stats`
--
ALTER TABLE `student_stats`
  ADD PRIMARY KEY (`StatID`),
  ADD UNIQUE KEY `MasterID` (`MasterID`),
  ADD KEY `fk_stats_master` (`MasterID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`),
  ADD KEY `users_ibfk_1` (`MasterID`),
  ADD KEY `Confirmed_Email` (`Confirmed_Email`) USING BTREE;

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_assignments`
--
ALTER TABLE `activity_assignments`
  MODIFY `AssignmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `activity_grades`
--
ALTER TABLE `activity_grades`
  MODIFY `GradeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `activity_groups`
--
ALTER TABLE `activity_groups`
  MODIFY `GroupID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `activity_requirements`
--
ALTER TABLE `activity_requirements`
  MODIFY `RequirementID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  MODIFY `BorrowedItemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=172;

--
-- AUTO_INCREMENT for table `borrowing_sessions`
--
ALTER TABLE `borrowing_sessions`
  MODIFY `SessionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `CategoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `ClassID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `class_enrollment`
--
ALTER TABLE `class_enrollment`
  MODIFY `EnrollmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `damaged_returns`
--
ALTER TABLE `damaged_returns`
  MODIFY `damage_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `group_logistics`
--
ALTER TABLE `group_logistics`
  MODIFY `LogisticsID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `MemberID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `ItemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `lab_activities`
--
ALTER TABLE `lab_activities`
  MODIFY `ActivityID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `lab_submissions`
--
ALTER TABLE `lab_submissions`
  MODIFY `SubmissionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `lookup_masterlist`
--
ALTER TABLE `lookup_masterlist`
  MODIFY `MasterID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `report_sections`
--
ALTER TABLE `report_sections`
  MODIFY `SectionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=261;

--
-- AUTO_INCREMENT for table `section_comments`
--
ALTER TABLE `section_comments`
  MODIFY `CommentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `section_history`
--
ALTER TABLE `section_history`
  MODIFY `HistoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `student_stats`
--
ALTER TABLE `student_stats`
  MODIFY `StatID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_assignments`
--
ALTER TABLE `activity_assignments`
  ADD CONSTRAINT `activity_assignments_ibfk_1` FOREIGN KEY (`ActivityID`) REFERENCES `lab_activities` (`ActivityID`) ON DELETE CASCADE,
  ADD CONSTRAINT `activity_assignments_ibfk_2` FOREIGN KEY (`ClassID`) REFERENCES `classes` (`ClassID`) ON DELETE CASCADE;

--
-- Constraints for table `activity_groups`
--
ALTER TABLE `activity_groups`
  ADD CONSTRAINT `fk_group_activity` FOREIGN KEY (`ActivityID`) REFERENCES `lab_activities` (`ActivityID`) ON DELETE CASCADE;

--
-- Constraints for table `activity_requirements`
--
ALTER TABLE `activity_requirements`
  ADD CONSTRAINT `fk_req_activity` FOREIGN KEY (`ActivityID`) REFERENCES `lab_activities` (`ActivityID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_req_inventory` FOREIGN KEY (`ItemID`) REFERENCES `inventory` (`ItemID`) ON DELETE CASCADE;

--
-- Constraints for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  ADD CONSTRAINT `fk_borrowed_inventory` FOREIGN KEY (`ItemID`) REFERENCES `inventory` (`ItemID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_borrowed_session` FOREIGN KEY (`SessionID`) REFERENCES `borrowing_sessions` (`SessionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_possessor` FOREIGN KEY (`Possessor_MasterID`) REFERENCES `lookup_masterlist` (`MasterID`);

--
-- Constraints for table `borrowing_sessions`
--
ALTER TABLE `borrowing_sessions`
  ADD CONSTRAINT `fk_session_activity` FOREIGN KEY (`ActivityID`) REFERENCES `lab_activities` (`ActivityID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_session_student` FOREIGN KEY (`StudentID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`TeacherID`) REFERENCES `users` (`UserID`);

--
-- Constraints for table `class_enrollment`
--
ALTER TABLE `class_enrollment`
  ADD CONSTRAINT `class_enrollment_ibfk_1` FOREIGN KEY (`ClassID`) REFERENCES `classes` (`ClassID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_enrollment_master` FOREIGN KEY (`MasterID`) REFERENCES `lookup_masterlist` (`MasterID`);

--
-- Constraints for table `damaged_returns`
--
ALTER TABLE `damaged_returns`
  ADD CONSTRAINT `damaged_returns_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `borrowing_sessions` (`SessionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `damaged_returns_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`ItemID`);

--
-- Constraints for table `group_logistics`
--
ALTER TABLE `group_logistics`
  ADD CONSTRAINT `group_logistics_ibfk_1` FOREIGN KEY (`ActivityID`) REFERENCES `lab_activities` (`ActivityID`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_logistics_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `activity_groups` (`GroupID`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_logistics_ibfk_3` FOREIGN KEY (`ItemID`) REFERENCES `inventory` (`ItemID`),
  ADD CONSTRAINT `group_logistics_ibfk_4` FOREIGN KEY (`AssignedToMasterID`) REFERENCES `lookup_masterlist` (`MasterID`);

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `fk_member_group` FOREIGN KEY (`GroupID`) REFERENCES `activity_groups` (`GroupID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_member_master` FOREIGN KEY (`MasterID`) REFERENCES `lookup_masterlist` (`MasterID`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`CategoryID`) REFERENCES `categories` (`CategoryID`);

--
-- Constraints for table `lab_submissions`
--
ALTER TABLE `lab_submissions`
  ADD CONSTRAINT `fk_sub_activity` FOREIGN KEY (`ActivityID`) REFERENCES `lab_activities` (`ActivityID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sub_group` FOREIGN KEY (`GroupID`) REFERENCES `activity_groups` (`GroupID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sub_student` FOREIGN KEY (`StudentID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `report_sections`
--
ALTER TABLE `report_sections`
  ADD CONSTRAINT `fk_section_locker` FOREIGN KEY (`Locked_By`) REFERENCES `lookup_masterlist` (`MasterID`) ON DELETE SET NULL,
  ADD CONSTRAINT `report_sections_ibfk_1` FOREIGN KEY (`ActivityID`) REFERENCES `lab_activities` (`ActivityID`) ON DELETE CASCADE,
  ADD CONSTRAINT `report_sections_ibfk_2` FOREIGN KEY (`GroupID`) REFERENCES `activity_groups` (`GroupID`) ON DELETE CASCADE;

--
-- Constraints for table `section_comments`
--
ALTER TABLE `section_comments`
  ADD CONSTRAINT `section_comments_ibfk_1` FOREIGN KEY (`SectionID`) REFERENCES `report_sections` (`SectionID`);

--
-- Constraints for table `section_history`
--
ALTER TABLE `section_history`
  ADD CONSTRAINT `section_history_ibfk_1` FOREIGN KEY (`SectionID`) REFERENCES `report_sections` (`SectionID`) ON DELETE CASCADE;

--
-- Constraints for table `student_stats`
--
ALTER TABLE `student_stats`
  ADD CONSTRAINT `fk_stats_master` FOREIGN KEY (`MasterID`) REFERENCES `lookup_masterlist` (`MasterID`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`MasterID`) REFERENCES `lookup_masterlist` (`MasterID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

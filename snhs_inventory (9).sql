-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 06, 2026 at 12:44 PM
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

-- --------------------------------------------------------

--
-- Table structure for table `activity_requirements`
--

CREATE TABLE `activity_requirements` (
  `RequirementID` int(11) NOT NULL,
  `ActivityID` int(11) NOT NULL,
  `ItemID` int(11) NOT NULL,
  `Required_Qty` int(11) NOT NULL,
  `VariantID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `Item_Status` enum('Pending','Issued','Returned','Damaged','Lost') DEFAULT 'Pending',
  `VariantID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `CategoryID` int(11) NOT NULL,
  `Category_Name` varchar(50) NOT NULL,
  `is_consumable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Non-Consumable, 1=Consumable'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `Description` text DEFAULT NULL,
  `is_consumable` tinyint(1) DEFAULT 0 COMMENT '0=Non-Consumable, 1=Consumable',
  `is_scalable` tinyint(1) DEFAULT 0 COMMENT '0=Fixed Size, 1=Has Multiple Sizes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_variants`
--

CREATE TABLE `item_variants` (
  `VariantID` int(11) NOT NULL,
  `ItemID` int(11) NOT NULL,
  `Size_Value` varchar(50) NOT NULL COMMENT 'e.g., 50, 100, 500',
  `Unit` varchar(20) DEFAULT 'ml' COMMENT 'e.g., ml, grams, mm',
  `Variant_Total_Qty` int(11) DEFAULT 0,
  `Variant_Available_Qty` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  ADD KEY `fk_req_inventory` (`ItemID`),
  ADD KEY `fk_req_variant` (`VariantID`);

--
-- Indexes for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  ADD PRIMARY KEY (`BorrowedItemID`),
  ADD KEY `fk_borrowed_session` (`SessionID`),
  ADD KEY `fk_borrowed_inventory` (`ItemID`),
  ADD KEY `fk_possessor` (`Possessor_MasterID`),
  ADD KEY `fk_borrowed_variant` (`VariantID`);

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
-- Indexes for table `item_variants`
--
ALTER TABLE `item_variants`
  ADD PRIMARY KEY (`VariantID`),
  ADD KEY `fk_variant_inventory` (`ItemID`);

--
-- Indexes for table `lab_activities`
--
ALTER TABLE `lab_activities`
  ADD PRIMARY KEY (`ActivityID`);

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
  MODIFY `AssignmentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_groups`
--
ALTER TABLE `activity_groups`
  MODIFY `GroupID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_requirements`
--
ALTER TABLE `activity_requirements`
  MODIFY `RequirementID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  MODIFY `BorrowedItemID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `borrowing_sessions`
--
ALTER TABLE `borrowing_sessions`
  MODIFY `SessionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `CategoryID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `ClassID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_enrollment`
--
ALTER TABLE `class_enrollment`
  MODIFY `EnrollmentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `damaged_returns`
--
ALTER TABLE `damaged_returns`
  MODIFY `damage_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_logistics`
--
ALTER TABLE `group_logistics`
  MODIFY `LogisticsID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `MemberID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `ItemID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_variants`
--
ALTER TABLE `item_variants`
  MODIFY `VariantID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_activities`
--
ALTER TABLE `lab_activities`
  MODIFY `ActivityID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lookup_masterlist`
--
ALTER TABLE `lookup_masterlist`
  MODIFY `MasterID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_sections`
--
ALTER TABLE `report_sections`
  MODIFY `SectionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT;

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
  ADD CONSTRAINT `fk_req_inventory` FOREIGN KEY (`ItemID`) REFERENCES `inventory` (`ItemID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_req_variant` FOREIGN KEY (`VariantID`) REFERENCES `item_variants` (`VariantID`) ON DELETE SET NULL;

--
-- Constraints for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  ADD CONSTRAINT `fk_borrowed_inventory` FOREIGN KEY (`ItemID`) REFERENCES `inventory` (`ItemID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_borrowed_session` FOREIGN KEY (`SessionID`) REFERENCES `borrowing_sessions` (`SessionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_borrowed_variant` FOREIGN KEY (`VariantID`) REFERENCES `item_variants` (`VariantID`) ON DELETE SET NULL,
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
  ADD CONSTRAINT `group_logistics_ibfk_4` FOREIGN KEY (`AssignedToMasterID`) REFERENCES `lookup_masterlist` (`Mast
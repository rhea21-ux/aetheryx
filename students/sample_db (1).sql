-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 29, 2026 at 12:54 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.3.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sample_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `course` varchar(20) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `tuition_fee` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `student_name`, `course`, `year_level`, `gender`, `age`, `tuition_fee`) VALUES
(1, 'John Cruz', 'BSIT', 1, 'Male', 18, 18500.00),
(2, 'Maria Santos', 'BSIT', 2, 'Female', 19, 19200.00),
(3, 'Peter Reyes', 'BSBA', 1, 'Male', 18, 17500.00),
(4, 'Angela Dela Cruz', 'BSIT', 3, 'Female', 21, 20500.00),
(5, 'Joshua Garcia', 'BSCS', 2, 'Male', 20, 21000.00),
(6, 'Nicole Ramos', 'BSCS', 4, 'Female', 22, 22500.00),
(7, 'Mark Lopez', 'BSBA', 3, 'Male', 21, 18000.00),
(8, 'Jenny Flores', 'BSIT', 4, 'Female', 22, 21500.00),
(9, 'Carl Mendoza', 'BSIT', 2, 'Male', 20, 19800.00),
(10, 'Princess Lim', 'BSBA', 1, 'Female', 18, 17000.00),
(11, 'Kevin Torres', 'BSCS', 3, 'Male', 21, 22000.00),
(12, 'Anna Villanueva', 'BSIT', 1, 'Female', 19, 18800.00),
(13, 'James Aquino', 'BSCS', 2, 'Male', 20, 20800.00),
(14, 'Rose Perez', 'BSBA', 4, 'Female', 23, 19500.00),
(15, 'Michael Tan', 'BSIT', 3, 'Male', 21, 21200.00);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

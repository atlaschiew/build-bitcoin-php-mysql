-- phpMyAdmin SQL Dump
-- version 4.9.5
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 22, 2021 at 01:24 AM
-- Server version: 5.6.50
-- PHP Version: 7.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `phpbuild_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `blocks`
--

CREATE TABLE `blocks` (
  `id` int(11) NOT NULL,
  `blockIndex` int(11) NOT NULL,
  `previousHash` char(64) NOT NULL,
  `timestamp` datetime NOT NULL,
  `data` mediumtext NOT NULL,
  `hash` char(64) NOT NULL,
  `difficulty` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `target` char(64) NOT NULL DEFAULT '0',
  `chainWork` char(64) NOT NULL,
  `nonce` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `blockTxIns`
--

CREATE TABLE `blockTxIns` (
  `id` int(11) NOT NULL,
  `address` char(34) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `txId` char(64) NOT NULL,
  `blockHash` char(64) NOT NULL,
  `blockIndex` int(11) NOT NULL,
  `txOutId` char(64) NOT NULL,
  `txOutIndex` int(3) NOT NULL,
  `signature` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `blockTxOuts`
--

CREATE TABLE `blockTxOuts` (
  `id` int(11) NOT NULL,
  `txId` char(64) NOT NULL,
  `txOutIndex` int(3) NOT NULL DEFAULT '0',
  `blockHash` char(64) NOT NULL,
  `blockIndex` int(11) NOT NULL,
  `address` char(34) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `amount` decimal(17,8) NOT NULL DEFAULT '0.00000000'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `blockTxs`
--

CREATE TABLE `blockTxs` (
  `id` int(11) NOT NULL,
  `txId` char(64) NOT NULL,
  `blockHash` char(64) NOT NULL,
  `blockIndex` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  `txFees` decimal(17,8) NOT NULL DEFAULT '0.00000000'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `fork`
--

CREATE TABLE `fork` (
  `id` int(11) NOT NULL,
  `status` enum('valid-fork','valid-headers','active') NOT NULL DEFAULT 'valid-fork',
  `chainWork` char(64) NOT NULL,
  `lastFork` char(64) NOT NULL,
  `branchStartAt` char(64) NOT NULL,
  `lastBlockIndex` int(11) NOT NULL,
  `lastBlockHash` char(64) NOT NULL,
  `repeatHear` smallint(6) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `peers`
--

CREATE TABLE `peers` (
  `id` int(11) NOT NULL,
  `host` varchar(20) NOT NULL,
  `lastUpdateDate` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transactionPool`
--

CREATE TABLE `transactionPool` (
  `txId` char(64) NOT NULL,
  `timestamp` datetime NOT NULL,
  `txFees` double(16,8) NOT NULL DEFAULT '0.00000000',
  `txIns` mediumtext NOT NULL,
  `txOuts` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transactionPoolTxIns`
--

CREATE TABLE `transactionPoolTxIns` (
  `id` int(11) NOT NULL,
  `txId` char(64) NOT NULL,
  `txOutId` char(64) NOT NULL,
  `txOutIndex` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `unspentTxOuts`
--

CREATE TABLE `unspentTxOuts` (
  `id` int(11) NOT NULL,
  `blockIndex` int(11) NOT NULL DEFAULT '0',
  `txOutId` char(64) NOT NULL,
  `txOutIndex` int(3) NOT NULL,
  `address` char(34) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `amount` decimal(17,8) NOT NULL DEFAULT '0.00000000'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `blocks`
--
ALTER TABLE `blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hash` (`hash`);

--
-- Indexes for table `blockTxIns`
--
ALTER TABLE `blockTxIns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `address` (`address`),
  ADD KEY `txId` (`blockHash`,`txId`) USING BTREE;

--
-- Indexes for table `blockTxOuts`
--
ALTER TABLE `blockTxOuts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `address` (`address`),
  ADD KEY `txId` (`blockHash`,`txId`,`txOutIndex`) USING BTREE;

--
-- Indexes for table `blockTxs`
--
ALTER TABLE `blockTxs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `txId` (`txId`,`blockHash`);

--
-- Indexes for table `fork`
--
ALTER TABLE `fork`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `status` (`status`,`lastBlockHash`);

--
-- Indexes for table `peers`
--
ALTER TABLE `peers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `host` (`host`);

--
-- Indexes for table `transactionPool`
--
ALTER TABLE `transactionPool`
  ADD PRIMARY KEY (`txId`);
ALTER TABLE `transactionPool` ADD FULLTEXT KEY `txIns` (`txIns`);
ALTER TABLE `transactionPool` ADD FULLTEXT KEY `txOuts` (`txOuts`);

--
-- Indexes for table `transactionPoolTxIns`
--
ALTER TABLE `transactionPoolTxIns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `txOutId_txOutIndex` (`txOutId`,`txOutIndex`) USING BTREE;

--
-- Indexes for table `unspentTxOuts`
--
ALTER TABLE `unspentTxOuts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `address_amount` (`address`,`amount`) USING BTREE,
  ADD KEY `txoutindex_txoutid` (`txOutIndex`,`txOutId`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blocks`
--
ALTER TABLE `blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blockTxIns`
--
ALTER TABLE `blockTxIns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blockTxOuts`
--
ALTER TABLE `blockTxOuts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blockTxs`
--
ALTER TABLE `blockTxs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fork`
--
ALTER TABLE `fork`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `peers`
--
ALTER TABLE `peers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactionPoolTxIns`
--
ALTER TABLE `transactionPoolTxIns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `unspentTxOuts`
--
ALTER TABLE `unspentTxOuts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

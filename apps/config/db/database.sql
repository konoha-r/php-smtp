
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- テーブルの構造 `mail_queue`
--

CREATE TABLE `mail_queue` (
  `id` bigint(20) NOT NULL DEFAULT '0',
  `create_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `time_to_send` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `sent_time` datetime DEFAULT NULL,
  `id_user` bigint(20) NOT NULL DEFAULT '0',
  `ip` varchar(20) COLLATE utf8_bin NOT NULL DEFAULT 'unknown',
  `sender` varchar(50) COLLATE utf8_bin NOT NULL DEFAULT '',
  `recipient` text COLLATE utf8_bin NOT NULL,
  `headers` text COLLATE utf8_bin NOT NULL,
  `body` longtext COLLATE utf8_bin NOT NULL,
  `try_sent` tinyint(4) NOT NULL DEFAULT '0',
  `delete_after_send` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `mail_queue`
--
ALTER TABLE `mail_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id` (`id`),
  ADD KEY `time_to_send` (`time_to_send`),
  ADD KEY `id_user` (`id_user`);
COMMIT;

--
-- テーブルの構造 `mail_queue_seq`
--

CREATE TABLE `mail_queue_seq` (
  `id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `mail_queue_seq`
--
ALTER TABLE `mail_queue_seq`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `mail_queue_seq`
--
ALTER TABLE `mail_queue_seq`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;



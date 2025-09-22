-- 백업에서 추출한 사용자 계정 복원
-- 기존 데이터를 삭제하고 백업 데이터로 교체

-- 사용자 삭제 (외래키 제약으로 인해 순서 중요)
DELETE FROM users WHERE id > 3; -- 개발자 계정은 유지

-- 시퀀스 재설정 (충돌 방지)
SELECT setval('users_id_seq', 100, false);

-- 백업 데이터 복원
INSERT INTO users (id, name, email, email_verified_at, password, role, branch_id, store_id, is_active, created_by_user_id, last_login_at, remember_token, created_at, updated_at) VALUES
(74, '천안두정점 매장', 'z006-001@ykp.com', NULL, '$2y$12$87Oic5aN39NgQuM06fgKZewjTRYIZGJhNWRko1UDhzi9Afs2DIYom', 'store', 17, 48, true, 14, NULL, 'fEXRuoDzeqKzStyCd4Q0GChLQIvK8zzv5YyTbMMRizNtKB7723iOEj0sMvQA', '2025-09-18 15:34:26+00', '2025-09-19 10:15:58+00'),
(47, '편민우', 'branch_z005@ykp.com', NULL, '$2y$12$dfInKFqW1fqEha8JEc7C7eT0UDpLsXuNtGa/RUs95SRJlaoh9W4r6', 'branch', 16, NULL, true, NULL, NULL, NULL, '2025-09-18 15:22:39+00', '2025-09-18 15:22:39+00'),
(54, '인천서구점 관리자', '인천서구점@ykp.com', NULL, '$2y$12$vONmJNJqGkXyRCHftVadAeaFxRfzcwwPJM6G/pfSV1YPuzRsAJcRa', 'store', 21, 28, true, 14, NULL, NULL, '2025-09-18 15:27:42+00', '2025-09-18 15:27:42+00'),
(55, '광주광산구점 매장', 'z009-002@ykp.com', NULL, '$2y$12$T810Cf.UZHYw63VS3X14NeoPHKPYwG/QS5l6iAo/FtZh0uuWOZ7Ha', 'store', 21, 29, true, 14, NULL, NULL, '2025-09-18 15:27:56+00', '2025-09-19 10:19:49+00'),
(81, '서울송파위례점 매장', 'z001-004@ykp.com', NULL, '$2y$12$UkoFIxZra4jWbOnSRK6Xnuy9DbgsCMZowC.cQSe34NUP2LcFg5DmK', 'store', 10, 55, true, 14, NULL, NULL, '2025-09-18 15:36:05+00', '2025-09-19 10:14:25+00'),
(80, '서울천호점 매장', 'z001-003@ykp.com', NULL, '$2y$12$.qr1eNrCHIIA5nmzoH51lur7k0IxoKLs295dnzrORdg5e25uprlke', 'store', 10, 54, true, 14, NULL, NULL, '2025-09-18 15:35:56+00', '2025-09-19 10:14:34+00'),
(36, '문다은', 'branch_z001@ykp.com', NULL, '$2y$12$3aFCJztwtYDvOBZCdaQhDuTYoI9yuDDUguMegcEslMDl9LaKES21m', 'branch', 10, NULL, true, NULL, NULL, NULL, '2025-09-17 17:06:41+00', '2025-09-19 10:14:51+00'),
(79, '전주송천점 매장', 'z001-002@ykp.com', NULL, '$2y$12$6Lu6Hgoa9Az1dijfhlLOjeLqsOJr5.MPa4RXvsFbDXvb3HG5c/L.e', 'store', 10, 53, true, 14, NULL, NULL, '2025-09-18 15:35:44+00', '2025-09-19 10:15:06+00'),
(77, '청주동남점 매장', 'z006-004@ykp.com', NULL, '$2y$12$Q78.QYMMQGbYo5bfcADUU.eCmooSFUs2rM7o2z/SjTcRuFhyIjgUa', 'store', 17, 51, true, 14, NULL, NULL, '2025-09-18 15:35:04+00', '2025-09-19 10:15:28+00'),
(78, '대전봉명점 매장', 'z006-005@ykp.com', NULL, '$2y$12$il8qELT5XXCW8Otsq8hYg.iuJp.Pse10Ocfh1CSbXStqoeM6zwQWe', 'store', 17, 52, true, 14, NULL, 'ydQJ6UvqHZxPH5pfG3dlztN8ty2P5w0mzrwDWDEzv1ZBfMymIyWFjEn37GsM', '2025-09-18 15:35:14+00', '2025-09-19 10:15:18+00'),
(75, '충주연수점 매장', 'z006-002@ykp.com', NULL, '$2y$12$IiTaVO/bNjD3mpBu46b8MeMCw5Rk0DPQfGGqV62zzEQ52qK41UC2S', 'store', 17, 49, true, 14, NULL, NULL, '2025-09-18 15:34:36+00', '2025-09-19 10:15:49+00'),
(76, '세종도담점 매장', 'z006-003@ykp.com', NULL, '$2y$12$37tWr68wUhz3F2yje2WjiOaMCgsjUEevt0c2H8mQ10SLwtLd/vr.q', 'store', 17, 50, true, 14, NULL, 'QGUhOw1w8nJ6t5Wss88onJ5RYuZ8Vvd7JBl1dw2nNXLQO4nvu4fVPArd4UI2', '2025-09-18 15:34:44+00', '2025-09-19 10:15:40+00'),
(73, '이충점 매장', 'z003-005@ykp.com', NULL, '$2y$12$aqVYtLtp1/muEux7BccUd.IGCXiN8yjDD/8Yz0.eiyDcjFWB8z4nK', 'store', 14, 47, true, 14, NULL, NULL, '2025-09-18 15:34:04+00', '2025-09-19 10:16:10+00'),
(72, '송내역점 매장', 'z003-004@ykp.com', NULL, '$2y$12$A9nTr8XFmijGG.OgPJEvx.LhuYlqpgVwehkPiQVO5oioe8GyZ/9VW', 'store', 14, 46, true, 14, NULL, NULL, '2025-09-18 15:33:56+00', '2025-09-19 10:16:17+00'),
(71, '계산점 매장', 'z003-003@ykp.com', NULL, '$2y$12$j6fQf/Ybew065gnpX6hVce/x/X.NcqOMOt.3fdSARdrMGh8AvV8PK', 'store', 14, 45, true, 14, NULL, NULL, '2025-09-18 15:33:48+00', '2025-09-19 10:16:28+00'),
(70, '부천상동점 매장', 'z003-002@ykp.com', NULL, '$2y$12$i0KgE/F8fgmwwI9sPuSu8OJ.JuRi/uUY1EV.PwatOdzD8uJbIwgwW', 'store', 14, 44, true, 14, NULL, NULL, '2025-09-18 15:33:40+00', '2025-09-19 10:16:55+00'),
(69, '부평점 매장', 'z003-001@ykp.com', NULL, '$2y$12$Hpsc.AYn533ZHj4WRjhfUu9Q0alw0zGdqKRKy6Yc7IRJkgNacCLMa', 'store', 14, 43, true, 14, NULL, NULL, '2025-09-18 15:33:31+00', '2025-09-19 10:17:09+00'),
(68, '마산양덕점 매장', 'z008-005@ykp.com', NULL, '$2y$12$2rt4uBx7aQ5/uyxJnZzojuYixPsy1FpowA3xsnbSwc0.frst38jL6', 'store', 20, 42, true, 14, NULL, NULL, '2025-09-18 15:33:09+00', '2025-09-19 10:17:23+00'),
(67, '정관점 매장', 'z008-004@ykp.com', NULL, '$2y$12$jNldA1aj10aTP3l8Fc18w.ZYteM71MxMdSWCmtcr55lvt9PEV33Cy', 'store', 20, 41, true, 14, NULL, NULL, '2025-09-18 15:33:01+00', '2025-09-19 10:17:35+00'),
(66, '충무공동점 매장', 'z008-003@ykp.com', NULL, '$2y$12$N//2ZyNFV.KFfelKiyW5Lu82kcxymRKifecVJ/TXie733c7ziZAfe', 'store', 20, 40, true, 14, NULL, NULL, '2025-09-18 15:32:54+00', '2025-09-19 10:17:42+00'),
(64, '명지점 매장', 'z008-001@ykp.com', NULL, '$2y$12$7kYRg5LeWG5klJhV.YrCc.GACuKJnESKvArPlz/OyimWUNnAhbf0K', 'store', 20, 38, true, 14, NULL, NULL, '2025-09-18 15:32:35+00', '2025-09-19 10:17:51+00'),
(63, '당산점 매장', 'z002-005@ykp.com', NULL, '$2y$12$B8ex1GytLb7Avn38lEskx.kFSo22rwX7.8c3P3GidH5DO67FnCMB.', 'store', 13, 37, true, 14, NULL, NULL, '2025-09-18 15:32:08+00', '2025-09-19 10:18:24+00'),
(62, '신방화점 매장', 'z002-004@ykp.com', NULL, '$2y$12$2CRO0E5cjhnXmP7VyD20SeVvNjEBOSlFIokG9GhkU1VNluzh18RJ2', 'store', 13, 36, true, 14, NULL, NULL, '2025-09-18 15:31:51+00', '2025-09-19 10:18:35+00'),
(61, '광흥창점 매장', 'z002-003@ykp.com', NULL, '$2y$12$JIImQK8X6moGC0/bK/8DtestpCYRpbwQ69zMZ4DQ1vXUiaXCFymzW', 'store', 13, 35, true, 14, NULL, NULL, '2025-09-18 15:31:44+00', '2025-09-19 10:18:43+00'),
(60, '수유점 매장', 'z002-002@ykp.com', NULL, '$2y$12$cqIpa1NZ/.H/78Ctj0R2oOqtcC7zv0fl9sV2PijnNJ8hl5a7oS1Uy', 'store', 13, 34, true, 14, NULL, NULL, '2025-09-18 15:31:36+00', '2025-09-19 10:18:51+00'),
(59, '이대역점 매장', 'z002-001@ykp.com', NULL, '$2y$12$FunhoQ5FlUnmjvkaC0E3derUfa7giJvDePKyV8sADBGWT.XO.Q/aC', 'store', 13, 33, true, 14, NULL, NULL, '2025-09-18 15:31:27+00', '2025-09-19 10:18:58+00'),
(58, '대구장기점 매장', 'z009-005@ykp.com', NULL, '$2y$12$W3J52z1Qdk87B09Y8lwQTe.yDX4Z9Bjryw49KQYH9r/laJ4JF6gIK', 'store', 21, 32, true, 14, NULL, NULL, '2025-09-18 15:28:33+00', '2025-09-19 10:19:08+00'),
(57, '광명점 매장', 'z009-004@ykp.com', NULL, '$2y$12$NJQ53LDpvq5E98j/qsFXL.uRxlQ0cPwGZ0c.y4W1oM66oYv4Rpace', 'store', 21, 31, true, 14, NULL, NULL, '2025-09-18 15:28:21+00', '2025-09-19 10:19:16+00'),
(56, '용인상현점 매장', 'z009-003@ykp.com', NULL, '$2y$12$D6Yl85xOE5Mt4wcqb5jZiem8U0jVGYxTTaVC7IgItItRALsMwm6HS', 'store', 21, 30, true, 14, NULL, NULL, '2025-09-18 15:28:10+00', '2025-09-19 10:19:24+00'),
(53, '인천서구점 매장', 'z009-001@ykp.com', NULL, '$2y$12$NcuLdCLKaxTPP6qyj1KhwOQRhZXOZxA84tNfbbKhvrK82jtc0itJS', 'store', 21, 28, true, 14, NULL, NULL, '2025-09-18 15:27:07+00', '2025-09-19 10:19:42+00'),
(48, '이원영', 'branch_z006@ykp.com', NULL, '$2y$12$b7t5P9gGq0Bujm8tjZypfuwgTKPHkSBFzdaij2547oHo3yPKd0fjm', 'branch', 17, NULL, true, NULL, NULL, NULL, '2025-09-18 15:22:51+00', '2025-09-19 10:20:05+00'),
(49, '강태영', 'branch_z007@ykp.com', NULL, '$2y$12$Yb1z0/z3dNx9s1KqiCYqdOzYQBsQG.9SMQvN8ViVNRU4XMuumCHSu', 'branch', 18, NULL, true, NULL, NULL, NULL, '2025-09-18 15:23:04+00', '2025-09-19 10:20:13+00'),
(46, '권도형', 'branch_z004@ykp.com', NULL, '$2y$12$HlVNjo0oC0qwn8dmaxwWv.duS91Sk2sTNIqXra/Qh8Q5VqIPg9r0G', 'branch', 15, NULL, true, NULL, NULL, NULL, '2025-09-18 15:22:27+00', '2025-09-19 10:20:35+00'),
(44, '김동현', 'branch_z002@ykp.com', NULL, '$2y$12$6q0v1gPGAa5i38NTGwoog.RjjSJocUoBzq53b0E8t8xuIoEmnU38y', 'branch', 13, NULL, true, NULL, NULL, NULL, '2025-09-18 15:22:04+00', '2025-09-19 10:20:45+00'),
(45, '김무성', 'branch_z003@ykp.com', NULL, '$2y$12$Y5S67IVuRM8ZmqX/ITrmROcroq8zfhCliG4aDtUccHeQusA3sTLk2', 'branch', 14, NULL, true, NULL, NULL, NULL, '2025-09-18 15:22:18+00', '2025-09-19 10:20:55+00'),
(52, '백진우', 'branch_z009@ykp.com', NULL, '$2y$12$yB/OrWUPeCap2GzJMo3Xl.q.7SL6LMDOUco8dKk2sr91Y65UgYRNu', 'branch', 21, NULL, true, NULL, NULL, NULL, '2025-09-18 15:24:16+00', '2025-09-19 10:21:07+00'),
(51, '반재훈', 'branch_z008@ykp.com', NULL, '$2y$12$uQXoNpipdhUzbNDCMcghtOKH09H7.ol1gGFc7Pud8cmWpljy3MYbq', 'branch', 20, NULL, true, NULL, NULL, NULL, '2025-09-18 15:23:56+00', '2025-09-19 10:21:16+00'),
(83, '전주고사점 매장', 'z001-006@ykp.com', NULL, '$2y$12$yA17ERKWk.cTy6g436wZHei9Qpw3oCC2aVH640nyzp8E4KrbVhXaG', 'store', 10, 57, true, 14, NULL, NULL, '2025-09-18 15:36:21+00', '2025-09-19 10:13:52+00'),
(82, '대구죽전점 매장', 'z001-005@ykp.com', NULL, '$2y$12$IyObaZTJ9Em5.wWwPxflD.D6QHWBq3KUQlaWrGIcZyXSerrAO48G2', 'store', 10, 56, true, 14, NULL, NULL, '2025-09-18 15:36:13+00', '2025-09-19 10:14:16+00'),
(65, '남포점 매장', 'z008-002@ykp.com', NULL, '$2y$12$870eLFMuGMMVLBv0h0T68uHkOF7Sx6ygf52J1hLSC39ECgbOc2C6u', 'store', 20, 39, true, 14, NULL, NULL, '2025-09-18 15:32:45+00', '2025-09-19 10:18:11+00'),
(14, '본사 관리자', 'hq@ykp.com', NULL, '$2y$12$PL5qcDIG6Y6e2w17oWyaCutX6M8rHIdSDKqHfLXxRcYVrT1d1nZTe', 'headquarters', NULL, NULL, true, NULL, NULL, 'pejzLW8uuGtonFdG36Q1BlCb4XNmi5H4Ju9OskVPv7o17ufRzY9RAG7rArxV', '2025-09-14 09:57:57.512025+00', '2025-09-14 23:09:25+00');

-- 시퀀스를 최대 ID로 재설정
SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));
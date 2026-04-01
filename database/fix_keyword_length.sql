-- Fix keyword column too short (128 -> 512)
ALTER TABLE app_group_keywords MODIFY keyword VARCHAR(512) NOT NULL;
ALTER TABLE app_group_members MODIFY matched_keyword VARCHAR(512);
ALTER TABLE video_group_keywords MODIFY keyword VARCHAR(512) NOT NULL;
ALTER TABLE video_group_members MODIFY matched_keyword VARCHAR(512);

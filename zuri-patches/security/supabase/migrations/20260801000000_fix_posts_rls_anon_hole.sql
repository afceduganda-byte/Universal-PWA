-- SECURITY FIX: Remove the `auth.uid() IS NULL` condition from posts RLS.
--
-- The previous migration (20250720093000-fix-posts-table-rls.sql) allowed
-- unauthenticated callers (auth.uid() IS NULL) to read AND write every row
-- in the posts table. This was a development-era workaround that was never
-- removed. This migration closes it.
--
-- After this migration, only:
--   1. Users whose profile.role = 'admin' in the profiles table, OR
--   2. Users whose email matches the owner email in profiles
-- can insert/update/delete posts. Anonymous visitors have zero write access.
-- Public read is kept (posts on the community page are publicly visible).

-- Step 1: Drop the vulnerable policy
DROP POLICY IF EXISTS "Admins can do everything on posts" ON posts;

-- Step 2: Recreate it without the auth.uid() IS NULL escape hatch
CREATE POLICY "Admins can do everything on posts"
ON posts
FOR ALL
USING (
  EXISTS (
    SELECT 1 FROM profiles
    WHERE id = auth.uid()
      AND role = 'admin'
  ) OR
  EXISTS (
    SELECT 1 FROM profiles
    WHERE id = auth.uid()
      AND email = 'zuriafricaadventures@gmail.com'
  )
)
WITH CHECK (
  EXISTS (
    SELECT 1 FROM profiles
    WHERE id = auth.uid()
      AND role = 'admin'
  ) OR
  EXISTS (
    SELECT 1 FROM profiles
    WHERE id = auth.uid()
      AND email = 'zuriafricaadventures@gmail.com'
  )
);

-- Step 3: Verify anonymous writes are now impossible.
-- (Run this manually in the SQL editor to confirm — should return 0 for anon role.)
-- SELECT count(*) FROM posts; -- should work (public read)
-- INSERT INTO posts (title) VALUES ('test'); -- should fail with RLS error when called as anon

# Equip Me Maybe: Team Warnings and Git Branching Guide

Read this fully before you touch the project. It takes 5 minutes and saves us hours.

---

## 1. WARNINGS (read first)

1. **Never run `php artisan db:seed` again.** The seeder is not safe to rerun yet. It crashes with `Duplicate entry 'Engineering'`. Reimon is fixing this.
2. **Never run `php artisan migrate:fresh` on a database you care about.** It deletes every table. Only use it on your own local database, and only when you are fine losing the data. After it, `php artisan migrate:fresh --seed` rebuilds the demo data.
3. **Never push `.env`.** It holds your database password. It is already in `.gitignore`. If `git status` ever shows `.env`, stop and tell the group.
4. **Never push `vendor/` or `node_modules/`.** Teammates rebuild them with `composer install` and `npm install`.
5. **Never push directly to `main`.** Always work on your own branch and open a Pull Request.
6. **Never use `git push --force`.** It can erase a teammate's work.
7. **Do not edit the same file as someone else at the same time.** See the file ownership table below.
8. **Run the tests before every push.** If they fail, do not push.

   ```
   php artisan test
   npm run build
   ```
9. **Migrations: add new ones, never edit old ones.** If you need a schema change, create a new migration file (`php artisan make:migration ...`). Editing an old migration breaks everyone's database.
10. **Pull before you start working each day.** Most conflicts come from working on an old copy.

---

## 2. Who owns what (to avoid conflicts)

| Member | Branch name | Main files they touch |
|---|---|---|
| Del Rosario, Reimon Anton | `feature/backend-fixes` | `app/Services/*`, `app/Models/*`, `app/Http/Controllers/Web/MaintenanceLogController.php`, `database/seeders/*` |
| Ibay, Jonathan Luke | `feature/qr-and-api` | `routes/api.php`, `app/Http/Controllers/Api/*`, `app/Http/Resources/*`, QR views |
| Ramos, Zachary Brent | `feature/ui-improvements` | `resources/views/layouts/*`, `resources/views/components/*`, `resources/css/*`, page styling |
| Jose, Colleen Margarette | `feature/tests-and-integration` | `tests/*`, merging, docs |

If you must edit a file outside your area, tell the group chat first.

---

## 3. One time setup (each member, Windows CMD)

Skip step 1 if you already have the project folder.

```
cd C:\
git clone <REPO_URL> equip-me-maybe-app
cd equip-me-maybe-app
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Open `.env` and set the database (XAMPP MySQL):

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=equip_me_maybe
DB_USERNAME=root
DB_PASSWORD=
```

Create an empty database named `equip_me_maybe` in phpMyAdmin, then:

```
php artisan migrate:fresh --seed
php artisan storage:link
npm run build
php artisan serve
```

Tell git who you are (once per computer):

```
git config --global user.name "Your Name"
git config --global user.email "your-school-email@s.ubaguio.edu"
```

---

## 4. Daily workflow: branch, commit, push, Pull Request

### Step 1. Start from the latest main

```
git checkout main
git pull origin main
```

### Step 2. Create (or switch to) your branch

First time:

```
git checkout -b feature/your-branch-name
```

Examples: `feature/backend-fixes`, `feature/qr-and-api`, `feature/ui-improvements`, `feature/tests-and-integration`.

Coming back another day:

```
git checkout feature/your-branch-name
git pull origin main
```

(The last line brings the newest main into your branch so you stay up to date.)

### Step 3. Do your work, then check what changed

```
git status
```

Make sure only files you meant to change are listed, and that `.env`, `vendor`, `node_modules` are not.

### Step 4. Run the tests

```
php artisan test
npm run build
```

### Step 5. Commit

```
git add .
git commit -m "Short clear message, e.g. Lock equipment row on reservation approval"
```

Commit small and often. One logical change per commit.

### Step 6. Push your branch (not main)

First push of a new branch:

```
git push -u origin feature/your-branch-name
```

Later pushes on the same branch:

```
git push
```

### Step 7. Open a Pull Request on GitHub

1. Open the repository on GitHub. Click **Compare & pull request** for your branch.
2. Base branch: `main`. Compare branch: yours.
3. Write what you changed and how to test it.
4. Request a review from Colleen (integrator).
5. Colleen merges after tests pass. Do not merge your own PR.

### Step 8. After your PR is merged

```
git checkout main
git pull origin main
git checkout feature/your-branch-name
git merge main
```

Then keep working on the next task on the same branch (or make a new branch from the updated main).

---

## 5. If something goes wrong

**Merge conflict after `git pull origin main`:**
1. Run `git status` and open the files marked "both modified".
2. Look for `<<<<<<<`, `=======`, `>>>>>>>`. Keep the correct code, delete the markers.
3. Then:
   ```
   git add .
   git commit -m "Resolve merge conflict"
   ```
4. If unsure, ask the group. Do not guess on someone else's code.

**I committed on main by mistake (not pushed yet):**
```
git branch feature/my-rescue-branch
git reset --hard origin/main
git checkout feature/my-rescue-branch
```

**I want to throw away my uncommitted changes in one file:**
```
git checkout -- path\to\file.php
```

**I accidentally added `.env` or `vendor`:**
```
git rm --cached .env
git rm -r --cached vendor
```
Then check `.gitignore` lists them and commit.

**Pulled new code and the app breaks:**
```
composer install
npm install
php artisan migrate
npm run build
php artisan optimize:clear
```
(Use `migrate`, not `migrate:fresh`, unless the group says to rebuild.)

---

## 6. Quick command cheat sheet

| Goal | Command |
|---|---|
| See current branch and changes | `git status` |
| Get latest main | `git checkout main` then `git pull origin main` |
| New branch | `git checkout -b feature/name` |
| Switch branch | `git checkout feature/name` |
| Stage and commit | `git add .` then `git commit -m "message"` |
| First push of a branch | `git push -u origin feature/name` |
| Later pushes | `git push` |
| See recent history | `git log --oneline -10` |
| Run tests | `php artisan test` |
| Rebuild frontend | `npm run build` |

---

## 7. Pull Request checklist (copy into every PR)

- [ ] I worked on my own branch, not main
- [ ] `php artisan test` passes
- [ ] `npm run build` succeeds
- [ ] No `.env`, `vendor`, or `node_modules` in my commit
- [ ] I did not edit old migrations (new ones only)
- [ ] I described what changed and how to test it

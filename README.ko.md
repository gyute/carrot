# CARROT

[![tests](https://github.com/gyute/carrot/actions/workflows/tests.yml/badge.svg)](https://github.com/gyute/carrot/actions/workflows/tests.yml)

[English](README.md) · [日本語](README.ja.md) · **한국어**

저작권 걱정 없는 샘플 사내 포털입니다. Laravel 13 + Inertia v3 (React) +
PostgreSQL, UI는 전부 일본어입니다.

> 영어판 [README.md](README.md)가 원본입니다. 내용이 어긋나면 그쪽이 맞습니다.

## 요구 사항

- PHP 8.4 이상, Composer
- Node 22 이상
- Docker (동봉된 PostgreSQL 컨테이너용)

## 설치

```bash
docker compose up -d          # PostgreSQL을 127.0.0.1:5432 에 띄웁니다
composer setup                # install, .env, app key, migrate, seed, npm install, build
composer run dev              # 서버 + 큐 워커 + vite + reverb + 로그
```

`composer run dev`는 `sandbox,default` 큐의 워커를 함께 띄웁니다. **이게
중요합니다.** 스크립트 실행과 알림이 전부 큐 작업이라, 워커가 없으면 계속
`待機中` 상태로 남습니다.

5432 포트가 이미 쓰이고 있다면 `.env.example`을 `.env`로 먼저 복사하고 `DB_PORT`를
비어 있는 포트(예: 5433)로 바꾸세요. compose가 `DB_PORT`가 가리키는 포트로
공개하고, Laravel도 같은 포트로 붙습니다.

`composer setup`은 몇 번을 다시 돌려도 안전합니다(시더가 이미 있는 건 건너뜁니다).
http://127.0.0.1:8000 을 열고 로그인하세요.

| 로그인 ID | 권한          | 비밀번호   |
| --------- | ------------- | ---------- |
| `test`    | 멤버          | `password` |
| `manager` | 부서 관리자   | `password` |
| `admin`   | 시스템 관리자 | `password` |

`/tools`는 비어 있는 상태로 시작합니다. 툴은 이 저장소의 코드가 아니라 누군가
등록하는 행이기 때문입니다. 내용이 있는 상태로 보고 싶다면:

```bash
php artisan demo:seed         # 데모 카탈로그 공개, --fresh 로 다시 만들기
```

`demo` / `demo-manager` / `demo-admin`(비밀번호 역시 `password`)을 추가하고, 샘플
툴 몇 개를 실제 승인 플로우에 태웁니다. 무엇이 공개되고 어떻게 추가하는지는
[`demo/README.md`](demo/README.md)를 보세요.

이미 구축된 환경에 변경을 받아온 뒤에는:

```bash
php artisan migrate
php artisan db:seed --force
```

## `.env`에서 손봐야 하는 값

`composer setup`이 `.env.example`을 복사하고, 그대로도 동작합니다. 아래는 한 번쯤
확인할 값들입니다.

| 키                                                    | 이유                                                                                                       |
| ----------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `DB_PORT`                                             | compose가 Postgres를 공개하는 포트. 5432가 막혀 있으면 바꿉니다                                            |
| `SANDBOX_DRIVER`                                      | `none`은 실행을 큐에 쌓기만 합니다. 로컬은 `bubblewrap`, 러너 호스트는 `docker`. 아니면 영원히 안 끝납니다 |
| `REVERB_APP_ID` / `_KEY` / `_SECRET`, `VITE_REVERB_*` | 실시간 갱신. 비워 두면 화면이 폴링으로 폴백할 뿐 망가지지는 않습니다                                       |
| `CATALOG_DEPARTMENTS`                                 | 所属(부서) 허용 목록, 쉼표 구분. 비우면 자유 입력이 됩니다                                                 |
| `PASSKEYS_USER_HANDLE_SECRET`                         | 기본값은 `APP_KEY`. `APP_KEY`를 교체할 일이 있다면 별도 고정값을 지정하세요                                |
| `LOG_CHANNEL`                                         | 시스템 화면이 tail 하는 채널. `daily`나 커스텀 경로도 따라갑니다                                           |

## 구조

| 경로                                                        | 들어 있는 것                                     |
| ----------------------------------------------------------- | ------------------------------------------------ |
| `routes/web.php`, `routes/settings.php`, `routes/tools.php` | 라우트(영역별로 분리)                            |
| `app/Http/Controllers/Tools/`                               | 툴 모듈                                          |
| `database/migrations/`                                      | `tools`, `tags`, `tag_tool`, `tool_submissions`  |
| `config/catalog.php`                                        | 所属 목록 (`CATALOG_DEPARTMENTS` 에서)           |
| `app/Sandbox/`                                              | 스크립트를 실행하는 샌드박스 러너                |
| `config/sandbox.php`, `docker/sandbox/`                     | 샌드박스 상한·드라이버·컨테이너 이미지           |
| `resources/js/pages/`                                       | Inertia 페이지 컴포넌트                          |
| `demo/`                                                     | `php artisan demo:seed`가 공개하는 데모 카탈로그 |
| `.ai/rules/`                                                | 편집 전에 알아야 할 결정 사항과 함정             |

## 툴 모듈

`/tools`는 사내 툴을 모읍니다. 코드로 push 하는 것은 없습니다. 툴은 `tools`
테이블의 행입니다.

- **종류**: `link`는 URL을 엽니다(외부 https 또는 포털 내부 경로). `embed`는 외부
  https 페이지를 툴 자신의 화면 안에 임베드합니다. `script`는 샌드박스에서
  스크립트를 실행합니다.
- 카탈로그는 상태·카테고리·所属으로 거를 수 있습니다. 비추천 툴은 참고용으로
  남지만 상태를 체크하기 전까지는 보이지 않습니다.
- **신청** (`tool_submissions`): 툴 등록, 동작 변경(URL / 스크립트 / 런타임 / 입력),
  비추천화는 초안 → 승인 대기 → 부서 승인 → 승인 / 반려를 거칩니다. 표시 항목
  (이름·요약·설명·아이콘·태그)은 소유자가 심사 없이 그 자리에서 고칩니다.
- **버전**: 승인할 때마다 승인 시각을 분 단위로 찍고(`202608271037`, 같은 분에 두
  번째면 `202608271037.2`), 신청자·부서 승인자·시스템 승인자를 기록합니다. 승인된
  신청이 곧 이력입니다.
- **알림**: 신청은 모든 리뷰어에게 메시지와 알림을 보내고(받은함은 `/inbox`, 헤더의
  종 아이콘) `/admin/approvals/{id}` 링크가 붙습니다. 판정은 신청자에게 돌아갑니다.
  Reverb가 실시간으로 밀어주지만 화면은 1분마다 폴링도 하므로, `reverb:start`가
  없는 개발 머신에서도 동작합니다.
- **권한**: `users.role`은 `member` / `manager` / `admin` 입니다. 신청자 부서의
  **부서 관리자**가 먼저 승인하고, 그다음 **시스템 관리자**가 공개합니다. 관리자는
  1단계에서 바로 공개할 수 있고, 부서 관리자가 없는 부서는 그대로 관리자에게
  넘어갑니다.

`/admin`은 DB 클라이언트 없이 모든 테이블을 다룹니다.

| 화면               | 편집하는 것                                               |
| ------------------ | --------------------------------------------------------- |
| `/admin/approvals` | 2단계 승인(부서 관리자는 자기 부서만)                     |
| `/admin/users`     | 권한과 所属 — `php artisan carrot:promote`와 같은 두 컬럼 |
| `/admin/tools`     | 삭제된 것 포함 모든 행: 비추천화·복구·완전 삭제           |
| `/admin/tags`      | 카테고리 태그 이름 변경과 병합                            |
| `/admin/runs`      | 샌드박스 실행 이력 조회·삭제·일괄 정리                    |
| `/admin/system`    | 큐·워커·샌드박스·Reverb·최근 실행·로그 tail               |

## 샌드박스

스크립트 툴이 앱 안에서 실행되는 일은 없습니다. `sandbox` 큐에 쌓인 `RunToolJob`이
승인된 소스를 다시 읽고, 실행을 요청할 때의 해시와 대조한 뒤 `SandboxRunner`에
넘깁니다.

| `SANDBOX_DRIVER` | 어디서                | 격리                                                                                                        |
| ---------------- | --------------------- | ----------------------------------------------------------------------------------------------------------- |
| `docker`         | 러너 호스트           | 일회용 컨테이너: `--network none`, 읽기 전용 루트, uid 65534, 모든 cap 제거, 메모리/CPU/PID 상한, `timeout` |
| `bubblewrap`     | Docker 없는 개발 머신 | 새 namespace, 네트워크 없음, 읽기 전용 루트, 전용 /tmp. 메모리는 ulimit. 프로덕션용은 아닙니다              |
| `fake`           | 테스트                | 아무것도 실행하지 않습니다                                                                                  |
| `none`           | 웹 호스트             | 큐에 쌓기만 합니다. 여기서 실행되면 예외를 던집니다                                                         |

스크립트 툴은 인터넷이 필요한지를 선언합니다(`config.network`: 기본 `none`, 또는
`internet`). 리뷰어는 승인 화면에서 그 선택을 강조 표시로 확인하고, 판정 전에
샌드박스에서 실행해 볼 수 있습니다. 러너는 `internet` 툴만
`SANDBOX_INTERNET_NETWORK`(기본 `bridge` — 아웃바운드를 통제할 수 있는 브리지를
지정하세요)에 붙이고, 나머지는 `--network none` 입니다.

입력은 `$TOOL_INPUTS`가 가리키는 JSON 파일로 전달되고, 표준 출력이 그대로 결과가
됩니다(`SANDBOX_OUTPUT_BYTES`에서 잘림). 실행은 사용자별로 제한되고
(`SANDBOX_RATE_LIMIT`/분), `SANDBOX_RUN_RETENTION_DAYS`가 지나면 스케줄된
`carrot:prune-runs`가 삭제합니다.

### 러너 호스트

HTTP를 서빙하지 않고 큐만 처리하는 별도 호스트에서 같은 코드베이스를 돌립니다:
`php artisan queue:work --queue=sandbox,default`. 필요한 것은 DB·큐·스토리지 자격
증명뿐입니다.

1. 비특권 계정(예: `carrot-runner`)을 `/etc/subuid`·`/etc/subgid` 범위와 함께
   만듭니다.
2. 그 계정으로 rootless Docker를 설치하고(`dockerd-rootless-setuptool.sh install`),
   `loginctl enable-linger carrot-runner`로 로그아웃 후에도 데몬이 남게 합니다.
   계정을 `docker` 그룹에 **절대 넣지 마세요.** root의 dockerd 소켓은 곧 root
   입니다. `DockerSandboxRunner`는 `docker info`가 rootless를 보고하지 않으면 시작을
   거부합니다(`SANDBOX_REQUIRE_ROOTLESS=false`는 개발 머신에서만).
3. 해당 사용자에게 cgroup v2 위임을 켭니다(`systemd` drop-in에
   `Delegate=cpu cpuset io memory pids`). 아니면 `--memory` / `--cpus` /
   `--pids-limit`이 무시됩니다.
4. 이미지는 CI에서 빌드하고(`docker/sandbox/README.md`) 호스트에서는 pull만 합니다.
   러너는 빌드하지 않습니다.
5. 러너의 `.env`에 `SANDBOX_DRIVER=docker`와
   `DOCKER_HOST=unix:///run/user/<uid>/docker.sock`을 설정합니다. 웹 호스트는
   `SANDBOX_DRIVER=none` 그대로 둡니다.

로컬에서는 `composer run dev`가 `sandbox,default` 워커와 Reverb를 웹 서버 옆에서
함께 돌리므로, `SANDBOX_DRIVER=bubblewrap`(또는 rootless 데몬과 함께 `docker`)로
두면 한 대에서 스크립트 툴이 끝까지 동작합니다. Reverb가 없으면 화면이 폴링으로
동작할 뿐입니다.

## 검사

```bash
composer test        # Pint, PHPStan, Pest
npm run types:check  # tsc
npm run lint         # eslint
```

CI는 push와 pull request마다 전부 실행합니다(`composer setup` 다음
`composer ci:check`, PHP 8.4 / Node 22).

### 테스트가 지키는 것

Pest 기반이며 대부분 피처 테스트, 전체가 몇 초입니다. 클래스를 직접 호출하지 않고
HTTP와 Inertia props를 두드리기 때문에 라우트·정책·화면 props가 한 번에 지켜집니다.

| 스위트                             | 지키는 것                                                                                                                                                                         |
| ---------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `tests/Feature/Tools/`             | 카탈로그: 무엇이 나열되는지, 태그 그룹과 개수, 임베드가 외부 https 오리진만 프레임한다는 규칙. 신청 플로우: 초안, 종류별 검증, 취하, 변경·비추천화 신청, 심사 없는 표시 항목 편집 |
| `tests/Feature/Admin/`             | 2단계 승인, 버전 스탬프(같은 분에 두 번째 포함), 슬러그 유일성, 반려. 관리자 화면: 권한과 所属, 삭제와 완전 삭제, 태그 이름 변경·병합, 실행 이력 정리, 시스템 상태                |
| `tests/Feature/Sandbox/`           | docker 명령의 모든 격리 플래그, 출력 상한, 네트워크 선택, 승인되지 않은 것을 실행하지 않는 소스 해시 검증, 사용자별 레이트 리밋, 실행 이력의 가시 범위와 정리                     |
| `tests/Feature/Inbox/`             | 각 단계에서 누구에게 메시지와 알림이 가는지, 읽음 상태, 메시지가 수신자 외에는 보이지 않는다는 것                                                                                 |
| `tests/Feature/DemoSeedTest.php`   | `demo:seed`가 실제 승인 플로우를 통해 공개한다는 것, 재실행해도 안전하다는 것, 프로덕션에서는 거부한다는 것                                                                       |
| `tests/Feature/Auth/`, `Settings/` | 로그인 ID 인증, 가입 검증, 비밀번호 재설정, 2단계 인증과 패스키 (스타터 킷에서 물려받음)                                                                                          |

두 스위트는 환경이 없으면 자동으로 건너뜁니다. `BubblewrapRunnerTest`는 `bwrap`
설치가, `DockerRunnerTest`는 `SANDBOX_DOCKER_TESTS=1`과 동작하는 Docker가
필요합니다. 나머지는 인메모리 SQLite를 상대로 어디서든 돌아갑니다.

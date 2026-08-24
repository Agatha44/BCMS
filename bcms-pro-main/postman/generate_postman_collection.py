#!/usr/bin/env python3
"""Generate BCMS_Pro_API.postman_collection.json from `php artisan route:list --path=api --json`."""
import json
import os
import re
import subprocess
import sys
import uuid


def load_routes(project_root: str) -> list:
    raw = subprocess.check_output(
        ["php", "artisan", "route:list", "--path=api", "--json"],
        cwd=project_root,
        stderr=subprocess.DEVNULL,
        text=True,
    )
    raw = raw.strip()
    if raw.startswith("["):
        return json.loads(raw)
    m = re.search(r"\[.*\]\s*$", raw, re.DOTALL)
    if not m:
        sys.stderr.write("Could not parse route list JSON\n")
        sys.exit(1)
    return json.loads(m.group(0))


def needs_sanctum(middleware: list) -> bool:
    return any("Authenticate:sanctum" in str(m) for m in middleware)


def method_primary(method: str) -> str:
    if "GET" in method:
        return "GET"
    if "POST" in method:
        return "POST"
    if "PUT" in method:
        return "PUT"
    if "DELETE" in method:
        return "DELETE"
    if "PATCH" in method:
        return "PATCH"
    return "GET"


def make_request(name: str, method: str, uri: str, use_bearer: bool) -> dict:
    segments = uri.split("/")
    # Replace {param} with :param style for Postman path variables (optional clarity)
    path_vars = []
    clean_segments = []
    for seg in segments:
        if seg.startswith("{") and seg.endswith("}"):
            pname = seg[1:-1]
            path_vars.append({"key": pname, "value": ""})
            clean_segments.append(":" + pname)
        else:
            clean_segments.append(seg)

    url_obj = {
        "raw": "{{base_url}}/" + "/".join(clean_segments),
        "host": ["{{base_url}}"],
        "path": clean_segments,
    }
    if path_vars:
        url_obj["variable"] = path_vars

    req = {
        "name": name,
        "request": {
            "method": method_primary(method),
            "header": [
                {"key": "Accept", "value": "application/json"},
            ],
            "url": url_obj,
        },
    }
    if method_primary(method) in ("POST", "PUT", "PATCH"):
        req["request"]["header"].append({"key": "Content-Type", "value": "application/json"})
        req["request"]["body"] = {
            "mode": "raw",
            "raw": "{}",
        }
    # Sanctum folder sets Bearer; omit auth so requests inherit.
    if not use_bearer:
        req["request"]["auth"] = {"type": "noauth"}
    return req


def main():
    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    routes = load_routes(project_root)

    sanctum_items = []
    public_items = []

    seen = set()
    for r in routes:
        uri = r.get("uri") or ""
        if not uri.startswith("api/"):
            continue
        # Skip web-only noise if any
        name = r.get("name") or uri
        method = r.get("method") or "GET"
        key = (method, uri)
        if key in seen:
            continue
        seen.add(key)

        display = f"{method_primary(method)} {uri}"
        if r.get("name"):
            display = f"{r['name']}"
        use_bearer = needs_sanctum(r.get("middleware") or [])
        item = make_request(display, method, uri, use_bearer)
        if use_bearer:
            sanctum_items.append(item)
        else:
            public_items.append(item)

    sanctum_items.sort(key=lambda x: x["name"])
    public_items.sort(key=lambda x: x["name"])

    login_tests = {
        "listen": "test",
        "script": {
            "exec": [
                "const json = pm.response.json();",
                "const token = json.data && json.data.token;",
                "if (token) {",
                "    pm.collectionVariables.set('token', token);",
                "    if (pm.environment.name) { pm.environment.set('token', token); }",
                "    console.log('Saved token to collection (and environment if active)');",
                "} else {",
                "    console.log('Login response had no data.token; body:', JSON.stringify(json));",
                "}",
            ],
            "type": "text/javascript",
        },
    }

    mobile_tests = {
        "listen": "test",
        "script": {
            "exec": [
                "try {",
                "  const json = pm.response.json();",
                "  const token = json.data && json.data.token;",
                "  if (token) {",
                "    pm.collectionVariables.set('token', token);",
                "    console.log('Saved mobile flow token to collection variable token');",
                "  }",
                "} catch (e) { console.log(e); }",
            ],
            "type": "text/javascript",
        },
    }

    auth_folder = {
        "name": "00 Authentication",
        "description": "Call **Auth / Login (staff Sanctum)** first. On success, `data.token` is saved to the collection variable `token`. Other folders use Bearer {{token}}.",
        "item": [
            {
                "name": "Login (staff Sanctum) — saves token",
                "event": [login_tests],
                "request": {
                    "auth": {"type": "noauth"},
                    "method": "POST",
                    "header": [
                        {"key": "Accept", "value": "application/json"},
                        {"key": "Content-Type", "value": "application/json"},
                    ],
                    "body": {
                        "mode": "raw",
                        "raw": '{\n  "username": "your_staff_username",\n  "password": "your_password"\n}',
                    },
                    "url": {
                        "raw": "{{base_url}}/api/auth/login",
                        "host": ["{{base_url}}"],
                        "path": ["api", "auth", "login"],
                    },
                    "description": "Staff back-office login (`AuthUser`). Response: `sendResponse` with `data.token`, `data.user`, `data.roles`.",
                },
            },
            {
                "name": "Logout",
                "request": {
                    "auth": {
                        "type": "bearer",
                        "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}],
                    },
                    "method": "POST",
                    "header": [
                        {"key": "Accept", "value": "application/json"},
                    ],
                    "url": {
                        "raw": "{{base_url}}/api/auth/logout",
                        "host": ["{{base_url}}"],
                        "path": ["api", "auth", "logout"],
                    },
                },
            },
            {
                "name": "Current user (auth/user)",
                "request": {
                    "auth": {
                        "type": "bearer",
                        "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}],
                    },
                    "method": "GET",
                    "header": [{"key": "Accept", "value": "application/json"}],
                    "url": {
                        "raw": "{{base_url}}/api/auth/user",
                        "host": ["{{base_url}}"],
                        "path": ["api", "auth", "user"],
                    },
                },
            },
            {
                "name": "Booth login (no Sanctum token)",
                "request": {
                    "auth": {"type": "noauth"},
                    "method": "POST",
                    "header": [
                        {"key": "Accept", "value": "application/json"},
                        {"key": "Content-Type", "value": "application/json"},
                    ],
                    "body": {
                        "mode": "raw",
                        "raw": '{\n  "username": "booth_user",\n  "password": "password"\n}',
                    },
                    "url": {
                        "raw": "{{base_url}}/api/auth/booth-login",
                        "host": ["{{base_url}}"],
                        "path": ["api", "auth", "booth-login"],
                    },
                    "description": "Returns session-style payload; does not issue the same Sanctum `data.token` as staff login.",
                },
            },
            {
                "name": "Bridge app authentication (mobile OTP) — may save token",
                "event": [mobile_tests],
                "request": {
                    "auth": {"type": "noauth"},
                    "method": "POST",
                    "header": [
                        {"key": "Accept", "value": "application/json"},
                        {"key": "Content-Type", "value": "application/json"},
                    ],
                    "body": {
                        "mode": "raw",
                        "raw": '{\n  "phone_number": "255700000000",\n  "otp": "123456"\n}',
                    },
                    "url": {
                        "raw": "{{base_url}}/api/bridge-app-authentication",
                        "host": ["{{base_url}}"],
                        "path": ["api", "bridge-app-authentication"],
                    },
                    "description": "Portal/mobile flow using `User` model. Token appears in `data.token` only after successful OTP verification.",
                },
            },
            make_request("forgot-password", "POST", "api/auth/forgot-password", False),
            make_request("verify-otp", "POST", "api/auth/verify-otp", False),
            make_request("reset-password", "POST", "api/auth/reset-password", False),
            make_request("update-password", "POST", "api/auth/update-password", False),
            make_request("test-cors", "GET", "api/auth/test-cors", False),
        ],
    }

    sanctum_folder = {
        "name": "01 Authenticated (Sanctum Bearer)",
        "description": "Requires **Login (staff Sanctum)** first. Uses `Authorization: Bearer {{token}}`.",
        "auth": {
            "type": "bearer",
            "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}],
        },
        "item": sanctum_items,
    }

    public_folder = {
        "name": "02 Public / no Sanctum (no Bearer)",
        "description": "These routes do not list `auth:sanctum` in `route:list`. They use **noauth**; adjust if your deployment adds middleware.",
        "item": public_items,
    }

    collection = {
        "info": {
            "_postman_id": str(uuid.uuid4()),
            "name": "BCMS Pro — Full API (generated)",
            "description": "Auto-generated from `php artisan route:list --path=api`.\\n\\n1. Set collection variable **base_url** (e.g. `http://127.0.0.1:8000`).\\n2. Run **00 Authentication / Login (staff Sanctum)** — token is stored in **token**.\\n3. Call **01 Authenticated** requests (folder-level Bearer).",
            "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        },
        "variable": [
            {"key": "base_url", "value": "http://127.0.0.1:8000"},
            {"key": "token", "value": ""},
        ],
        "item": [auth_folder, sanctum_folder, public_folder],
    }

    out = os.path.join(project_root, "postman", "BCMS_Pro_API.postman_collection.json")
    with open(out, "w", encoding="utf-8") as f:
        json.dump(collection, f, indent=2)
    print("Wrote", out, "—", len(sanctum_items), "sanctum,", len(public_items), "public")


if __name__ == "__main__":
    main()

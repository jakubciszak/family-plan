#!/usr/bin/env bash
# Przebieg E2E po API. Uzycie: e2e.sh <base-url>
U=${1:-http://localhost:8080}
TS=$(date +%s%N | tail -c 8)
D=$(mktemp -d)
CO=$D/owner.txt; CM=$D/member.txt
EO="rodzic+$TS@example.com"; EM="dziecko+$TS@example.com"; P="TestoweHaslo123"
PASS=0; FAIL=0

check() { # opis oczekiwany-kod metoda url dane ciastka
  local desc=$1 want=$2 m=$3 u=$4 d=$5 ck=$6
  local code
  if [ -n "$d" ]; then
    code=$(curl -sS -o $D/b.txt -X "$m" "$U$u" -H 'Content-Type: application/json' -b "$ck" -c "$ck" -d "$d" -w '%{http_code}')
  else
    code=$(curl -sS -o $D/b.txt -X "$m" "$U$u" -H 'Content-Type: application/json' -b "$ck" -c "$ck" -w '%{http_code}')
  fi
  if [ "$code" = "$want" ]; then
    PASS=$((PASS+1)); printf '  OK   %-46s %s\n' "$desc" "$code"
  else
    FAIL=$((FAIL+1)); printf '  FAIL %-46s oczekiwano %s, jest %s\n' "$desc" "$want" "$code"
    if grep -q "DOCTYPE html" $D/b.txt; then echo "         (strona bledu HTML)"; else echo "         $(head -c 200 $D/b.txt | tr -d '\n')"; fi
  fi
}
j() { python3 -c "import json;print(json.load(open('$D/b.txt'))$1)" 2>/dev/null; }

echo "== $U =="
echo "Uwierzytelnianie"
check "rejestracja rodzica"        201 POST /api/auth/register "{\"name\":\"Rodzic\",\"email\":\"$EO\",\"password\":\"$P\"}" $CO
check "logowanie rodzica"          200 POST /api/auth/login "{\"email\":\"$EO\",\"password\":\"$P\"}" $CO
OWNER=$(j "['user']['id']")
check "sesja rodzica"              200 GET  /api/auth/me "" $CO
check "rejestracja dziecka"        201 POST /api/auth/register "{\"name\":\"Dziecko\",\"email\":\"$EM\",\"password\":\"$P\"}" $CM
check "logowanie dziecka"          200 POST /api/auth/login "{\"email\":\"$EM\",\"password\":\"$P\"}" $CM
MEMBER=$(j "['user']['id']")
check "zle haslo odrzucone"        401 POST /api/auth/login "{\"email\":\"$EO\",\"password\":\"zle\"}" $D/x.txt
check "duplikat maila odrzucony"   400 POST /api/auth/register "{\"name\":\"X\",\"email\":\"$EO\",\"password\":\"$P\"}" $D/x.txt

echo "Zespoly"
check "utworzenie zespolu"         201 POST /api/teams '{"name":"Dom","description":"rodzina"}' $CO
TEAM=$(j "['id']")
check "lista zespolow"             200 GET  /api/teams "" $CO
check "czlonkowie zespolu"         200 GET  "/api/teams/$TEAM/members" "" $CO
check "edycja zespolu"             200 PUT  "/api/teams/$TEAM" '{"name":"Dom 2","description":"v2"}' $CO
check "zaproszenie do zespolu"     201 POST "/api/teams/$TEAM/invite" "{\"email\":\"$EM\",\"role\":\"member\"}" $CO
check "lista zaproszen dziecka"    200 GET  /api/teams/invitations "" $CM
TOKEN=$(j "['invitations'][0]['token']")
check "przyjecie zaproszenia"      200 POST "/api/teams/invitations/$TOKEN/accept" "" $CM
check "zespol widoczny u dziecka"  200 GET  /api/teams "" $CM

echo "Zadania"
check "utworzenie zadania"         201 POST /api/tasks "{\"name\":\"Odkurzyc\",\"points\":30,\"frequency\":\"weekly\",\"teamId\":\"$TEAM\",\"createdBy\":\"$OWNER\"}" $CO
TASK=$(j "['id']")
check "lista zadan"                200 GET  /api/tasks "" $CO
check "pojedyncze zadanie"         200 GET  "/api/tasks/$TASK" "" $CO
check "przypisanie zadania"        200 POST "/api/tasks/$TASK/assign" "{\"userId\":\"$MEMBER\"}" $CO
check "wykonanie przez dziecko"    200 POST "/api/tasks/$TASK/complete" "{\"userId\":\"$MEMBER\"}" $CM
check "ZATWIERDZENIE przez rodzica" 200 POST "/api/tasks/$TASK/approve" "{}" $CO
check "zadanie bez teamId odrzucone" 400 POST /api/tasks "{\"name\":\"X\",\"points\":1,\"frequency\":\"once\",\"createdBy\":\"$OWNER\"}" $CO

echo "Punkty"
curl -sS -o $D/b.txt -b $CM "$U/api/users/$MEMBER/points"
BAL=$(j "['balance']")
if [ "$BAL" = "30" ]; then PASS=$((PASS+1)); printf '  OK   %-46s %s\n' "saldo dziecka po zatwierdzeniu" "$BAL"
else FAIL=$((FAIL+1)); printf '  FAIL %-46s oczekiwano 30, jest %s\n' "saldo dziecka po zatwierdzeniu" "$BAL"; fi

echo "Autoryzacja"
check "utworzenie drugiego zadania" 201 POST /api/tasks "{\"name\":\"Druga\",\"points\":10,\"frequency\":\"once\",\"teamId\":\"$TEAM\",\"createdBy\":\"$OWNER\"}" $CO
T2=$(j "['id']")
check "przypisanie drugiego"       200 POST "/api/tasks/$T2/assign" "{\"userId\":\"$MEMBER\"}" $CO
check "wykonanie drugiego"         200 POST "/api/tasks/$T2/complete" "{\"userId\":\"$MEMBER\"}" $CM
check "dziecko NIE zatwierdza"     403 POST "/api/tasks/$T2/approve" "{}" $CM
check "anonim nie widzi zespolow"  401 GET  /api/teams "" $D/anon.txt

echo "Pozostale"
check "szablony zadan"             200 GET  /api/task-templates "" $CO
check "ustawienia uzytkownika"     200 GET  "/api/user-settings/$OWNER" "" $CO
check "lista uzytkownikow"         200 GET  /api/users "" $CO
check "wylogowanie"                200 POST /api/auth/logout "" $CO

echo
echo "PASS=$PASS FAIL=$FAIL"
rm -rf $D
[ "$FAIL" = "0" ]

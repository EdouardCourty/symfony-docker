#!/bin/bash

# Script de génération de la clé SSH pour le déploiement GitHub Actions.
# Lance-le UNE FOIS sur ta machine locale, puis suis les instructions affichées.

set -e

KEY_PATH="${HOME}/.ssh/app_github_deploy"
KEY_COMMENT="github-actions-app-deploy"

echo ""
echo "════════════════════════════════════════════════════════"
echo "  Générateur de clé SSH — Déploiement GitHub Actions"
echo "════════════════════════════════════════════════════════"
echo ""

if [ -f "$KEY_PATH" ]; then
    echo "⚠️  ATTENTION : Une clé existe déjà à : $KEY_PATH"
    echo ""
    echo "  Si tu la remplaces :"
    echo "    - La clé publique actuellement dans ~/.ssh/authorized_keys"
    echo "      du serveur de production ne fonctionnera plus."
    echo "    - Le secret GitHub SSH_DEPLOY_KEY devra être mis à jour."
    echo "    - Tout pipeline de déploiement actif sera cassé jusqu'à"
    echo "      la mise à jour du secret."
    echo ""
    read -r -p "Veux-tu quand même générer une nouvelle clé ? (oui/non) : " CONFIRM
    if [ "$CONFIRM" != "oui" ]; then
        echo ""
        echo "Annulé. La clé existante est conservée."
        echo ""
        exit 0
    fi
    echo ""
    echo "Remplacement de la clé existante..."
    rm -f "$KEY_PATH" "${KEY_PATH}.pub"
fi

echo "Génération de la clé Ed25519..."
ssh-keygen -t ed25519 -C "$KEY_COMMENT" -f "$KEY_PATH" -N ""
chmod 600 "$KEY_PATH"
chmod 644 "${KEY_PATH}.pub"

echo ""
echo "✅ Clé générée avec succès !"
echo ""
echo "════════════════════════════════════════════════════════"
echo "  ÉTAPE 1 — Ajouter la clé publique au serveur de prod"
echo "════════════════════════════════════════════════════════"
echo ""
echo "Connecte-toi au serveur (en tant que root, deploy.sh l'exige) et exécute :"
echo ""
echo "  echo \"$(cat "${KEY_PATH}.pub")\" >> ~/.ssh/authorized_keys"
echo ""
echo "════════════════════════════════════════════════════════"
echo "  ÉTAPE 2 — Ajouter les secrets dans GitHub"
echo "════════════════════════════════════════════════════════"
echo ""
echo "Rends-toi sur : Settings → Secrets and variables → Actions"
echo "du repository GitHub, et ajoute les secrets suivants :"
echo ""
echo "  SSH_DEPLOY_KEY  → contenu de la clé privée ci-dessous"
echo "  SSH_HOST        → IP ou hostname du serveur de production"
echo "  SSH_USER        → utilisateur SSH (root, deploy.sh doit tourner en root)"
echo ""
echo "──── Clé privée (contenu de $KEY_PATH) ────"
echo ""
cat "$KEY_PATH"
echo ""
echo "────────────────────────────────────────────"
echo ""
echo "⚠️  Ne partage JAMAIS cette clé privée. Elle donne accès complet au serveur."
echo ""

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparatif des Prestataires de Paiement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
            color: #333;
        }
        h1 {
            text-align: center;
            margin-bottom: 40px;
        }
        h2 {
            margin-top: 30px;
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 12px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f4f6f8;
            width: 50%;
        }
        ul {
            margin: 0;
            padding-left: 20px;
        }
        .tarifs {
            background-color: #eef2f7;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>

    <h1>Comparatif des solutions de paiement</h1>

    <!-- Stripe -->
    <h2>Stripe</h2>
    <table>
        <thead>
            <tr>
                <th>Avantages</th>
                <th>Inconvénients</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <ul>
                        <li>Intégration très flexible et complète pour développeurs</li>
                        <li>Accepte une multitude de devises et moyens de paiement locaux</li>
                        <li>Formulaires de paiement hautement personnalisables (Stripe Elements)</li>
                        <li>Excellente documentation technique</li>
                    </ul>
                </td>
                <td>
                    <ul>
                        <li>Demande plus de compétences techniques pour une personnalisation avancée</li>
                        <li>Support client parfois difficile à joindre par téléphone</li>
                        <li>Politique stricte sur le risque (blocage automatique en cas de suspicion)</li>
                    </ul>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="tarifs">
                    <strong>Tarifs :</strong> ~1.5% + 0.25 € par transaction (cartes européennes standard) — Sans abonnement
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Mollie -->
    <h2>Mollie</h2>
    <table>
        <thead>
            <tr>
                <th>Avantages</th>
                <th>Inconvénients</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <ul>
                        <li>Interface très simple à prendre en main et à installer</li>
                        <li>Excellente couverture des moyens de paiement européens (iDEAL, Bancontact, CB...)</li>
                        <li>Support client réactif et très orienté marché européen</li>
                        <li>Tableau de bord clair pour le suivi des remboursements et paiements</li>
                    </ul>
                </td>
                <td>
                    <ul>
                        <li>Moins d'options sur-mesure que Stripe pour des architectures complexes</li>
                        <li>Moins connu par le grand public hors de l'Europe</li>
                    </ul>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="tarifs">
                    <strong>Tarifs :</strong> ~1.2% + 0.25 € par transaction (cartes EEE) — Sans abonnement
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- PayPal -->
    <h2>PayPal</h2>
    <table>
        <thead>
            <tr>
                <th>Avantages</th>
                <th>Inconvénients</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <ul>
                        <li>Confiance et notoriété maximales auprès des acheteurs</li>
                        <li>Permet le paiement sans saisir ses coordonnées bancaires</li>
                        <li>Protection des acheteurs et des vendeurs intégrée</li>
                        <li>Mise en place extrêmement rapide</li>
                    </ul>
                </td>
                <td>
                    <ul>
                        <li>Frais de transaction plus élevés que la concurrence</li>
                        <li>Risque de blocage temporaire de fonds en cas de litige client</li>
                        <li>L'utilisateur est souvent redirigé hors de votre site pour valider</li>
                    </ul>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="tarifs">
                    <strong>Tarifs :</strong> ~2.9% + 0.35 € par transaction (variable selon volume) — Sans abonnement
                </td>
            </tr>
        </tfoot>
    </table>

</body>
</html>
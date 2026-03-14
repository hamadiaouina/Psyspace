const express = require('express');
const cors = require('cors');
const { genererResumePro } = require('./ia');

const app = express();

// Autorise ton site PHP (localhost) à parler à Node.js
app.use(cors()); 
app.use(express.json());

// La route que ton fichier PHP va appeler
app.post('/resume', async (req, res) => {
    const { texte } = req.body;
    
    console.log("📩 Nouvelle demande d'analyse reçue...");

    try {
        const resultat = await genererResumePro(texte);
        res.json({ result: resultat });
        console.log("✅ Analyse envoyée avec succès.");
    } catch (err) {
        res.status(500).json({ error: "Erreur serveur Node : " + err.message });
    }
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log(`\n---------------------------------`);
    console.log(`🚀 SERVEUR PSYSPACE IA ACTIF`);
    console.log(`🔗 URL : http://localhost:${PORT}`);
    console.log(`---------------------------------\n`);
});
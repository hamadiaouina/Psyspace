const Groq = require('groq-sdk');

// Ton moteur d'intelligence artificielle
const groq = new Groq({ 
    apiKey: "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" 
});

async function genererResumePro(textePatient) {
    try {
        const completion = await groq.chat.completions.create({
            model: "llama-3.3-70b-versatile",
            messages: [
                { 
                    role: "system", 
                    content: `Tu es un assistant spécialisé en psychologie.
                    Analyse la transcription de la consultation et réponds UNIQUEMENT en JSON sous cette forme :
                    {
                      "synthese": "Résumé des points clés",
                      "humeur": "Score sur 10",
                      "mots_cles": ["mot1", "mot2"]
                    }
                    Ne parle pas du futur ou de risques suicidaires.`
                },
                { role: "user", content: textePatient }
            ],
            response_format: { type: "json_object" },
            temperature: 0.1
        });

        const data = JSON.parse(completion.choices[0].message.content);
        
        // On transforme le JSON en texte structuré pour l'affichage PHP
        let resultatFinal = `### 📝 SYNTHÈSE CLINIQUE\n${data.synthese}\n\n`;
        resultatFinal += `### 📊 SCORE D'HUMEUR : ${data.humeur}/10\n\n`;
        resultatFinal += `### 🧠 MOTS CLÉS\n- ${data.mots_cles.join('\n- ')}`;

        return resultatFinal;
    } catch (error) {
        console.error("Erreur Groq:", error);
        return "❌ Erreur : Impossible de joindre l'IA pour le moment.";
    }
}

module.exports = { genererResumePro };
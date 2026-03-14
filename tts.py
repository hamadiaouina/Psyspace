#!/usr/bin/env python3
"""
tts.py — Edge TTS Microsoft (gratuit, illimité)
Usage : python3 tts.py "Texte à dire" output.mp3
"""
import sys, asyncio, os

async def speak(text, output):
    try:
        import edge_tts
    except ImportError:
        # Installer edge-tts si pas présent
        os.system(f"{sys.executable} -m pip install edge-tts --quiet")
        import edge_tts

    communicate = edge_tts.Communicate(text, voice="fr-FR-HenriNeural")
    await communicate.save(output)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        sys.exit(1)
    text   = sys.argv[1]
    output = sys.argv[2]
    asyncio.run(speak(text, output))
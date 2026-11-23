import sys
from pathlib import Path

# Permet "import main" depuis le dossier ml-service.
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

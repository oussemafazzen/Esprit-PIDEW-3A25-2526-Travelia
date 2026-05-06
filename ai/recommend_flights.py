"""
Batch runtime inference for Travelia's ML flight recommender.

This script performs no manual scoring. It loads the trained scikit-learn model
from ai/model/flight_recommender.pkl and returns model-predicted recommendation
scores for the displayed flight offers.
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

import joblib
import pandas as pd


ROOT_DIR = Path(__file__).resolve().parents[1]
MODEL_PATH = ROOT_DIR / "ai" / "model" / "flight_recommender.pkl"

BADGE_LABELS = {
    "budget": {"label": "Best Budget Choice", "icon": "\u2b50"},
    "comfort": {"label": "Best Comfort Choice", "icon": "\u2728"},
    "balanced": {"label": "Best Overall", "icon": "\ud83c\udfaf"},
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Predict ML recommendation scores for displayed flights.")
    parser.add_argument("--batch-json", required=True, help="Use '-' to read JSON from stdin.")
    parser.add_argument("--profile", choices=["budget", "comfort", "balanced"], default="balanced")
    return parser.parse_args()


def normalize_row(row: dict, profile: str) -> dict:
    return {
        "destination": str(row.get("destination", "unknown")),
        "current_price": float(row.get("current_price", 0)),
        "duration_minutes": int(row.get("duration_minutes", 120)),
        "stops_count": int(row.get("stops_count", 0)),
        "travel_class": str(row.get("travel_class", "economy")),
        "offer_type": str(row.get("offer_type", row.get("travel_class", "economy"))),
        "airline": str(row.get("airline", "unknown")),
        "departure_hour": int(row.get("departure_hour", 9)),
        "profile": profile,
    }


def main() -> None:
    try:
        args = parse_args()

        if not MODEL_PATH.exists():
            print(json.dumps({"recommendations": [], "error": "model_not_found"}))
            return

        if args.batch_json == "-":
            # Use buffer for binary-safe reading on Windows (avoids codepage issues)
            # Try utf-8-sig first to strip any BOM that Windows/PHP may prepend
            raw_bytes = sys.stdin.buffer.read()
            try:
                raw_payload = raw_bytes.decode("utf-8-sig").strip()
            except UnicodeDecodeError:
                raw_payload = raw_bytes.decode("utf-8", errors="replace").strip()
        else:
            raw_payload = args.batch_json.strip()

        if not raw_payload:
            print(json.dumps({"recommendations": [], "error": "empty_batch_payload"}))
            return

        rows = json.loads(raw_payload)

        if not isinstance(rows, list):
            print(json.dumps({"recommendations": [], "error": "batch_payload_must_be_array"}))
            return

        valid_rows = [row for row in rows if isinstance(row, dict)]

        if not valid_rows:
            print(json.dumps({"recommendations": []}))
            return

        model = joblib.load(MODEL_PATH)
        features = pd.DataFrame([normalize_row(row, args.profile) for row in valid_rows])
        scores = model.predict(features)
        scores = [int(round(max(0, min(100, float(score))))) for score in scores]
        ranked_indexes = sorted(range(len(scores)), key=lambda position: scores[position], reverse=True)
        rank_by_position = {position: rank for rank, position in enumerate(ranked_indexes, start=1)}
        best_position = ranked_indexes[0]
        badge = BADGE_LABELS[args.profile]

        recommendations = []
        for position, row in enumerate(valid_rows):
            badges = []
            if position == best_position:
                badges.append({
                    "profile": args.profile,
                    "label": badge["label"],
                    "icon": badge["icon"],
                })

            recommendations.append({
                "index": int(row.get("index", position)),
                "score": scores[position],
                "rank": rank_by_position[position],
                "badges": badges,
            })

        print(json.dumps({
            "profile": args.profile,
            "recommendations": recommendations,
        }))
    except Exception as exc:
        print(json.dumps({"recommendations": [], "error": str(exc)}))


if __name__ == "__main__":
    main()

"""
Predict the best booking action from the trained scikit-learn model.

Symfony can call this script for one flight through CLI arguments or for many
flights at once by passing --batch-json with a JSON array. Batch mode keeps the
AI real while avoiding one Python process per displayed flight card.
"""

import argparse
import json
import sys
from pathlib import Path

import joblib
import pandas as pd


ROOT_DIR = Path(__file__).resolve().parents[1]
MODEL_PATH = ROOT_DIR / "ai" / "model" / "flight_model.pkl"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Predict flight booking recommendation.")
    parser.add_argument("--destination", required=False, default="unknown")
    parser.add_argument("--current-price", required=False, type=float, default=0)
    parser.add_argument("--days-before-departure", required=False, type=int, default=0)
    parser.add_argument("--month", required=False, type=int, default=1)
    parser.add_argument("--is-weekend", required=False, type=int, choices=[0, 1], default=0)
    parser.add_argument("--travel-class", required=False, default="economy")
    parser.add_argument("--stops-count", required=False, type=int, default=0)
    parser.add_argument("--duration-minutes", required=False, type=int, default=120)
    parser.add_argument("--departure-hour", required=False, type=int, default=9)
    parser.add_argument("--airline", required=False, default="unknown")
    parser.add_argument("--batch-json", required=False, help="JSON array of flight feature objects.")
    return parser.parse_args()


def build_feature_row(item: dict) -> dict:
    """Return one dataframe row with the exact columns used during training."""
    return {
        "destination": item.get("destination", "unknown"),
        "current_price": float(item.get("current_price", 0)),
        "days_before_departure": int(item.get("days_before_departure", 0)),
        "month": int(item.get("month", 1)),
        "is_weekend": int(item.get("is_weekend", 0)),
        "travel_class": item.get("travel_class", "economy"),
        "stops_count": int(item.get("stops_count", 0)),
        "duration_minutes": int(item.get("duration_minutes", 120)),
        "departure_hour": int(item.get("departure_hour", 9)),
        "airline": item.get("airline", "unknown"),
    }


def main() -> None:
    try:
        args = parse_args()

        if not MODEL_PATH.exists():
            print(json.dumps({"label": None, "error": "model_not_found"}))
            return

        model = joblib.load(MODEL_PATH)

        if args.batch_json:
            raw_batch_json = sys.stdin.read() if args.batch_json == "-" else args.batch_json
            rows = json.loads(raw_batch_json)

            if not isinstance(rows, list):
                print(json.dumps({"predictions": [], "error": "batch_payload_must_be_array"}))
                return

            features = pd.DataFrame([build_feature_row(row) for row in rows if isinstance(row, dict)])

            if features.empty:
                print(json.dumps({"predictions": []}))
                return

            labels = model.predict(features)
            print(json.dumps({"predictions": [str(label) for label in labels]}))
            return

        # The dataframe column names must match the training dataset columns.
        features = pd.DataFrame([build_feature_row({
            "destination": args.destination,
            "current_price": args.current_price,
            "days_before_departure": args.days_before_departure,
            "month": args.month,
            "is_weekend": args.is_weekend,
            "travel_class": args.travel_class,
            "stops_count": args.stops_count,
            "duration_minutes": args.duration_minutes,
            "departure_hour": args.departure_hour,
            "airline": args.airline,
        })])

        label = model.predict(features)[0]
        print(json.dumps({"label": str(label)}))
    except Exception as exc:
        print(json.dumps({"predictions": [], "label": None, "error": str(exc)}))


if __name__ == "__main__":
    main()

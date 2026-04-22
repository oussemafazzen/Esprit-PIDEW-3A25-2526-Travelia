"""
Train Travelia's ML flight recommendation model.

The project does not yet have historical booking logs, so this script creates a
behaviorally realistic training set by simulating many search sessions, traveler
profiles, and probabilistic choices. Runtime recommendations do not use these
simulation rules; they only use the trained scikit-learn model saved here.
"""

from __future__ import annotations

import csv
import random
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import GradientBoostingRegressor, RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder


ROOT_DIR = Path(__file__).resolve().parents[1]
DATASET_PATH = ROOT_DIR / "data" / "ai" / "flight_recommendation_data.csv"
MODEL_PATH = ROOT_DIR / "ai" / "model" / "flight_recommender.pkl"
RANDOM_SEED = 42

DESTINATIONS = {
    "Paris": {"base_price": 250, "base_duration": 150, "premium": 1.05},
    "Madrid": {"base_price": 220, "base_duration": 135, "premium": 0.96},
    "London": {"base_price": 290, "base_duration": 180, "premium": 1.12},
    "Rome": {"base_price": 240, "base_duration": 110, "premium": 0.98},
    "Istanbul": {"base_price": 310, "base_duration": 170, "premium": 1.02},
    "Dubai": {"base_price": 520, "base_duration": 330, "premium": 1.20},
    "Doha": {"base_price": 480, "base_duration": 310, "premium": 1.15},
    "Casablanca": {"base_price": 210, "base_duration": 165, "premium": 0.92},
    "Cairo": {"base_price": 330, "base_duration": 210, "premium": 1.0},
    "New York": {"base_price": 760, "base_duration": 650, "premium": 1.30},
}

AIRLINES = [
    ("Tunisair", 0.58, 0.95),
    ("Air France", 0.82, 1.12),
    ("Lufthansa", 0.86, 1.15),
    ("Turkish Airlines", 0.80, 1.05),
    ("Emirates", 0.94, 1.28),
    ("Qatar Airways", 0.93, 1.24),
    ("ITA Airways", 0.70, 1.02),
    ("Vueling", 0.48, 0.82),
    ("Transavia", 0.45, 0.78),
]

TRAVEL_CLASSES = {
    "economy": {"comfort": 0.46, "price_factor": 1.00},
    "eco": {"comfort": 0.40, "price_factor": 0.88},
    "standard": {"comfort": 0.62, "price_factor": 1.10},
    "flex": {"comfort": 0.76, "price_factor": 1.25},
    "business": {"comfort": 0.95, "price_factor": 1.85},
}

PROFILES = ["budget", "comfort", "balanced"]


def make_encoder() -> OneHotEncoder:
    try:
        return OneHotEncoder(handle_unknown="ignore", sparse_output=False)
    except TypeError:
        return OneHotEncoder(handle_unknown="ignore", sparse=False)


def normalize_inverse(values: list[float]) -> list[float]:
    minimum = min(values)
    maximum = max(values)
    if maximum <= minimum:
        return [1.0 for _ in values]
    return [(maximum - value) / (maximum - minimum) for value in values]


def normalize_positive(values: list[float]) -> list[float]:
    minimum = min(values)
    maximum = max(values)
    if maximum <= minimum:
        return [1.0 for _ in values]
    return [(value - minimum) / (maximum - minimum) for value in values]


def sample_profile_preferences(profile: str) -> dict[str, float]:
    """Sample varied hidden preferences instead of using one fixed formula."""
    if profile == "budget":
        return {
            "price": random.uniform(0.55, 0.82),
            "duration": random.uniform(0.06, 0.22),
            "stops": random.uniform(0.04, 0.18),
            "comfort": random.uniform(0.00, 0.12),
            "airline": random.uniform(0.00, 0.08),
            "schedule": random.uniform(0.00, 0.08),
        }
    if profile == "comfort":
        return {
            "price": random.uniform(0.05, 0.22),
            "duration": random.uniform(0.26, 0.48),
            "stops": random.uniform(0.20, 0.38),
            "comfort": random.uniform(0.16, 0.34),
            "airline": random.uniform(0.04, 0.16),
            "schedule": random.uniform(0.04, 0.14),
        }
    return {
        "price": random.uniform(0.25, 0.48),
        "duration": random.uniform(0.16, 0.34),
        "stops": random.uniform(0.12, 0.28),
        "comfort": random.uniform(0.06, 0.20),
        "airline": random.uniform(0.02, 0.12),
        "schedule": random.uniform(0.02, 0.12),
    }


def generate_offer(destination: str) -> dict[str, object]:
    destination_info = DESTINATIONS[destination]
    airline, airline_quality, airline_price = random.choice(AIRLINES)
    travel_class = random.choices(
        population=list(TRAVEL_CLASSES.keys()),
        weights=[0.42, 0.22, 0.20, 0.12, 0.04],
        k=1,
    )[0]
    class_info = TRAVEL_CLASSES[travel_class]
    stops_count = random.choices([0, 1, 2], weights=[0.58, 0.32, 0.10], k=1)[0]
    departure_hour = random.choices(
        list(range(5, 24)),
        weights=[2, 2, 4, 7, 9, 9, 8, 6, 5, 4, 4, 6, 8, 9, 8, 6, 4, 3, 2],
        k=1,
    )[0]

    duration = (
        destination_info["base_duration"]
        + stops_count * random.randint(45, 125)
        + random.randint(-25, 40)
    )
    duration = max(55, duration)

    price = (
        destination_info["base_price"]
        * destination_info["premium"]
        * airline_price
        * class_info["price_factor"]
        * random.uniform(0.82, 1.28)
        + stops_count * random.uniform(-25, 45)
    )
    price = max(70, price)

    offer_type = travel_class if travel_class in ["eco", "standard", "flex"] else "standard"

    return {
        "destination": destination,
        "current_price": round(price, 2),
        "duration_minutes": int(duration),
        "stops_count": stops_count,
        "travel_class": travel_class,
        "offer_type": offer_type,
        "airline": airline,
        "airline_quality": airline_quality,
        "class_comfort": class_info["comfort"],
        "departure_hour": departure_hour,
    }


def schedule_score(hour: int) -> float:
    if 8 <= hour <= 11 or 17 <= hour <= 20:
        return 1.0
    if 6 <= hour <= 22:
        return 0.72
    return 0.38


def generate_dataset(session_count: int = 550) -> list[dict[str, object]]:
    random.seed(RANDOM_SEED)
    np.random.seed(RANDOM_SEED)
    rows: list[dict[str, object]] = []

    for _ in range(session_count):
        destination = random.choice(list(DESTINATIONS.keys()))
        offer_count = random.randint(5, 10)
        offers = [generate_offer(destination) for _ in range(offer_count)]

        price_scores = normalize_inverse([float(offer["current_price"]) for offer in offers])
        duration_scores = normalize_inverse([float(offer["duration_minutes"]) for offer in offers])
        stop_scores = normalize_inverse([float(offer["stops_count"]) for offer in offers])
        class_scores = normalize_positive([float(offer["class_comfort"]) for offer in offers])
        airline_scores = normalize_positive([float(offer["airline_quality"]) for offer in offers])
        schedule_scores = [schedule_score(int(offer["departure_hour"])) for offer in offers]

        for profile in PROFILES:
            preferences = sample_profile_preferences(profile)
            utilities = []

            for idx, offer in enumerate(offers):
                utility = (
                    preferences["price"] * price_scores[idx]
                    + preferences["duration"] * duration_scores[idx]
                    + preferences["stops"] * stop_scores[idx]
                    + preferences["comfort"] * class_scores[idx]
                    + preferences["airline"] * airline_scores[idx]
                    + preferences["schedule"] * schedule_scores[idx]
                    + np.random.normal(0, 0.055)
                )
                utilities.append(utility)

            # Softmax-like conversion creates probabilistic, human-like choice
            # tendencies instead of deterministic labels.
            exp_utilities = np.exp(np.array(utilities) * random.uniform(2.2, 4.2))
            probabilities = exp_utilities / exp_utilities.sum()
            chosen_index = int(np.random.choice(range(len(offers)), p=probabilities))
            max_probability = float(probabilities.max())

            for idx, offer in enumerate(offers):
                closeness_bonus = max(0.0, utilities[idx] - min(utilities)) / max(0.001, max(utilities) - min(utilities))
                chosen_bonus = 10 if idx == chosen_index else 0
                recommendation_score = (
                    18
                    + 58 * (float(probabilities[idx]) / max_probability)
                    + 18 * closeness_bonus
                    + chosen_bonus
                    + np.random.normal(0, 4.5)
                )
                recommendation_score = int(round(max(0, min(100, recommendation_score))))

                rows.append({
                    "destination": offer["destination"],
                    "current_price": offer["current_price"],
                    "duration_minutes": offer["duration_minutes"],
                    "stops_count": offer["stops_count"],
                    "travel_class": offer["travel_class"],
                    "offer_type": offer["offer_type"],
                    "airline": offer["airline"],
                    "departure_hour": offer["departure_hour"],
                    "profile": profile,
                    "recommendation_score": recommendation_score,
                })

    return rows


def write_dataset(rows: list[dict[str, object]]) -> None:
    DATASET_PATH.parent.mkdir(parents=True, exist_ok=True)
    with DATASET_PATH.open("w", newline="", encoding="utf-8") as csv_file:
        writer = csv.DictWriter(csv_file, fieldnames=[
            "destination",
            "current_price",
            "duration_minutes",
            "stops_count",
            "travel_class",
            "offer_type",
            "airline",
            "departure_hour",
            "profile",
            "recommendation_score",
        ])
        writer.writeheader()
        writer.writerows(rows)


def train_model() -> None:
    rows = generate_dataset()
    write_dataset(rows)
    data = pd.DataFrame(rows)

    features = [
        "destination",
        "current_price",
        "duration_minutes",
        "stops_count",
        "travel_class",
        "offer_type",
        "airline",
        "departure_hour",
        "profile",
    ]
    target = "recommendation_score"
    numeric_features = ["current_price", "duration_minutes", "stops_count", "departure_hour"]
    categorical_features = ["destination", "travel_class", "offer_type", "airline", "profile"]

    x_train, x_test, y_train, y_test = train_test_split(
        data[features],
        data[target],
        test_size=0.2,
        random_state=RANDOM_SEED,
    )

    preprocessor = ColumnTransformer([
        ("categorical", make_encoder(), categorical_features),
        ("numeric", "passthrough", numeric_features),
    ])
    candidates = {
        "RandomForestRegressor": RandomForestRegressor(
            n_estimators=260,
            min_samples_leaf=4,
            random_state=RANDOM_SEED,
            n_jobs=1,
        ),
        "GradientBoostingRegressor": GradientBoostingRegressor(
            n_estimators=220,
            learning_rate=0.05,
            max_depth=4,
            random_state=RANDOM_SEED,
        ),
    }
    best_name = ""
    best_pipeline: Pipeline | None = None
    best_rmse = float("inf")

    print(f"Dataset saved to {DATASET_PATH}")
    print(f"Rows: {len(data)}")
    print("Selection criterion: lowest RMSE")
    print("Evaluation metrics:")

    for name, model in candidates.items():
        pipeline = Pipeline([
            ("preprocessor", preprocessor),
            ("model", model),
        ])
        pipeline.fit(x_train, y_train)
        predictions = pipeline.predict(x_test)
        mae = mean_absolute_error(y_test, predictions)
        rmse = float(np.sqrt(mean_squared_error(y_test, predictions)))
        r2 = r2_score(y_test, predictions)
        print(f"- {name}: MAE={mae:.2f}, RMSE={rmse:.2f}, R2={r2:.3f}")

        if rmse < best_rmse:
            best_name = name
            best_pipeline = pipeline
            best_rmse = rmse

    MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(best_pipeline, MODEL_PATH)
    print(f"Best model: {best_name}")
    print(f"Model saved to {MODEL_PATH}")


if __name__ == "__main__":
    train_model()

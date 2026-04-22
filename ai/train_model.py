"""
Train and compare ML models for Travelia flight booking advice.

The objective is not only high accuracy: we select the best model by macro F1
so each class (buy_now, wait, increase_risk) matters equally. This is important
because a model can have good accuracy while still failing the minority class.
"""

from pathlib import Path

import joblib
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import GradientBoostingClassifier, RandomForestClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix, f1_score
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler
from sklearn.tree import DecisionTreeClassifier


ROOT_DIR = Path(__file__).resolve().parents[1]
DATASET_PATH = ROOT_DIR / "data" / "ai" / "flight_data.csv"
MODEL_PATH = ROOT_DIR / "ai" / "model" / "flight_model.pkl"

NUMERIC_FEATURES = [
    "current_price",
    "days_before_departure",
    "month",
    "is_weekend",
    "stops_count",
    "duration_minutes",
    "departure_hour",
]

CATEGORICAL_FEATURES = [
    "destination",
    "travel_class",
    "airline",
]

TARGET_COLUMN = "label"


def build_preprocessor() -> ColumnTransformer:
    """Create preprocessing for mixed tabular flight data."""
    return ColumnTransformer(
        transformers=[
            ("numeric", StandardScaler(), NUMERIC_FEATURES),
            ("categorical", OneHotEncoder(handle_unknown="ignore"), CATEGORICAL_FEATURES),
        ]
    )


def candidate_models() -> dict:
    """Models compared during training; all are real scikit-learn estimators."""
    return {
        "logistic_regression": LogisticRegression(
            max_iter=2000,
            class_weight="balanced",
            multi_class="auto",
        ),
        "decision_tree": DecisionTreeClassifier(
            max_depth=9,
            min_samples_leaf=8,
            class_weight="balanced",
            random_state=42,
        ),
        "random_forest": RandomForestClassifier(
            n_estimators=240,
            max_depth=12,
            min_samples_leaf=4,
            class_weight="balanced",
            random_state=42,
        ),
        "gradient_boosting": GradientBoostingClassifier(
            n_estimators=180,
            learning_rate=0.06,
            max_depth=4,
            random_state=42,
        ),
    }


def main() -> None:
    if not DATASET_PATH.exists():
        raise FileNotFoundError(f"Dataset not found: {DATASET_PATH}")

    data = pd.read_csv(DATASET_PATH)
    feature_columns = NUMERIC_FEATURES + CATEGORICAL_FEATURES
    X = data[feature_columns]
    y = data[TARGET_COLUMN]

    print("Dataset rows:", len(data))
    print("Label distribution:")
    print(y.value_counts().to_string())
    print()

    X_train, X_test, y_train, y_test = train_test_split(
        X,
        y,
        test_size=0.2,
        random_state=42,
        stratify=y,
    )

    best_name = None
    best_pipeline = None
    best_macro_f1 = -1.0

    for name, estimator in candidate_models().items():
        pipeline = Pipeline(
            steps=[
                ("preprocessor", build_preprocessor()),
                ("model", estimator),
            ]
        )

        pipeline.fit(X_train, y_train)
        predictions = pipeline.predict(X_test)

        accuracy = accuracy_score(y_test, predictions)
        macro_f1 = f1_score(y_test, predictions, average="macro")
        weighted_f1 = f1_score(y_test, predictions, average="weighted")

        print(f"=== {name} ===")
        print(f"Accuracy:    {accuracy:.3f}")
        print(f"Macro F1:    {macro_f1:.3f}")
        print(f"Weighted F1: {weighted_f1:.3f}")
        print("Confusion matrix:")
        print(confusion_matrix(y_test, predictions, labels=["buy_now", "wait", "increase_risk"]))
        print("Classification report:")
        print(classification_report(y_test, predictions))
        print()

        if macro_f1 > best_macro_f1:
            best_name = name
            best_macro_f1 = macro_f1
            best_pipeline = pipeline

    if best_pipeline is None:
        raise RuntimeError("No model was trained.")

    MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(best_pipeline, MODEL_PATH)

    print(f"Best model: {best_name}")
    print(f"Best macro F1: {best_macro_f1:.3f}")
    print(f"Saved model to: {MODEL_PATH}")


if __name__ == "__main__":
    main()

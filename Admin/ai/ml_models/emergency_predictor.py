import sys
import json
import pickle
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor, RandomForestClassifier
from sklearn.preprocessing import LabelEncoder, StandardScaler
import os

class EmergencyPredictor:
    def __init__(self):
        self.model_path = os.path.join(os.path.dirname(__file__))
        self.regression_model = None
        self.classification_model = None
        self.scaler = None
        self.label_encoders = {}
        
        # Load models if they exist
        self.load_models()
        
        # If models don't exist, train them with sample data
        if not self.regression_model:
            self.train_models()
    
    def load_models(self):
        """Load pre-trained models"""
        try:
            with open(os.path.join(self.model_path, 'response_time_model.pkl'), 'rb') as f:
                self.regression_model = pickle.load(f)
            
            with open(os.path.join(self.model_path, 'risk_classifier.pkl'), 'rb') as f:
                self.classification_model = pickle.load(f)
            
            with open(os.path.join(self.model_path, 'scaler.pkl'), 'rb') as f:
                self.scaler = pickle.load(f)
                
            # Load label encoders
            for feature in ['incident_type', 'priority', 'weather_conditions']:
                with open(os.path.join(self.model_path, f'encoder_{feature}.pkl'), 'rb') as f:
                    self.label_encoders[feature] = pickle.load(f)
                    
        except FileNotFoundError:
            # Models don't exist yet, they will be trained
            self.regression_model = None
            self.classification_model = None
    
    def save_models(self):
        """Save trained models"""
        os.makedirs(self.model_path, exist_ok=True)
        
        with open(os.path.join(self.model_path, 'response_time_model.pkl'), 'wb') as f:
            pickle.dump(self.regression_model, f)
        
        with open(os.path.join(self.model_path, 'risk_classifier.pkl'), 'wb') as f:
            pickle.dump(self.classification_model, f)
        
        with open(os.path.join(self.model_path, 'scaler.pkl'), 'wb') as f:
            pickle.dump(self.scaler, f)
            
        # Save label encoders
        for feature, encoder in self.label_encoders.items():
            with open(os.path.join(self.model_path, f'encoder_{feature}.pkl'), 'wb') as f:
                pickle.dump(encoder, f)
    
    def create_sample_data(self):
        """Create sample training data for demonstration"""
        # This would normally come from your database
        data = {
            'incident_type': ['structure-fire', 'vehicle-fire', 'medical', 'rescue', 'hazmat'] * 100,
            'priority': ['low', 'medium', 'high', 'critical'] * 125,
            'time_of_day': list(range(0, 24)) * 21,
            'day_of_week': list(range(1, 8)) * 36,
            'latitude': [14.6762] * 500,
            'longitude': [121.0439] * 500,
            'units_count': np.random.randint(1, 5, 500),
            'weather_conditions': ['clear', 'rainy', 'cloudy', 'windy'] * 125,
            'response_time': np.random.uniform(5, 30, 500),
            'risk_level': np.random.choice(['low', 'medium', 'high'], 500, p=[0.3, 0.5, 0.2])
        }
        
        return pd.DataFrame(data)
    
    def train_models(self):
        """Train machine learning models"""
        print("Training models with sample data...", file=sys.stderr)
        
        # Create sample data
        df = self.create_sample_data()
        
        # Encode categorical variables
        for feature in ['incident_type', 'priority', 'weather_conditions', 'risk_level']:
            le = LabelEncoder()
            df[feature] = le.fit_transform(df[feature])
            self.label_encoders[feature] = le
        
        # Prepare features and target for regression (response time prediction)
        X_reg = df[['incident_type', 'priority', 'time_of_day', 'day_of_week', 'units_count', 'weather_conditions']]
        y_reg = df['response_time']
        
        # Prepare features and target for classification (risk level prediction)
        X_clf = df[['incident_type', 'priority', 'time_of_day', 'units_count', 'weather_conditions']]
        y_clf = df['risk_level']
        
        # Scale features
        self.scaler = StandardScaler()
        X_reg_scaled = self.scaler.fit_transform(X_reg)
        X_clf_scaled = self.scaler.transform(X_clf)  # Use same scaler
        
        # Train regression model
        self.regression_model = RandomForestRegressor(n_estimators=100, random_state=42)
        self.regression_model.fit(X_reg_scaled, y_reg)
        
        # Train classification model
        self.classification_model = RandomForestClassifier(n_estimators=100, random_state=42)
        self.classification_model.fit(X_clf_scaled, y_clf)
        
        # Save models
        self.save_models()
        
        print("Models trained and saved successfully.", file=sys.stderr)
    
    def predict_response_time(self, features):
        """Predict response time for given features"""
        if not self.regression_model:
            raise ValueError("Regression model not trained")
        
        # Scale features
        features_scaled = self.scaler.transform([features])
        
        # Predict
        prediction = self.regression_model.predict(features_scaled)
        return max(3, prediction[0])  # Minimum 3 minutes
    
    def predict_risk_level(self, features):
        """Predict risk level for given features"""
        if not self.classification_model:
            raise ValueError("Classification model not trained")
        
        # Scale features
        features_scaled = self.scaler.transform([features])
        
        # Predict
        prediction = self.classification_model.predict(features_scaled)
        return self.label_encoders['risk_level'].inverse_transform(prediction)[0]
    
   def predict_incident_outcome(self, incident_data):
        """Make predictions for an incident"""
        try:
            # Prepare features for prediction
            features_reg = self.prepare_features(incident_data)
            
            # Make predictions
            response_time = self.predict_response_time(features_reg)
            risk_level = self.predict_risk_level(features_reg[:5])  # Use first 5 features for classification
            
            # Additional predictions based on risk level
            if risk_level == 'high':
                containment_time = response_time * 3 + np.random.uniform(30, 60)
                evacuation_likelihood = 'high'
            elif risk_level == 'medium':
                containment_time = response_time * 2 + np.random.uniform(15, 30)
                evacuation_likelihood = 'medium'
            else:
                containment_time = response_time * 1.5 + np.random.uniform(5, 15)
                evacuation_likelihood = 'low'
            
            return {
                'success': True,
                'predictions': {
                    'estimated_response_time': round(response_time, 1),
                    'risk_level': risk_level,
                    'estimated_containment_time': round(containment_time, 1),
                    'evacuation_likelihood': evacuation_likelihood,
                    'resource_requirements': risk_level,
                    'property_damage_risk': risk_level
                }
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'error': 'No input data provided'}))
        sys.exit(1)
    
    # Read input data from file
    input_file = sys.argv[1]
    try:
        with open(input_file, 'r') as f:
            incident_data = json.load(f)
    except Exception as e:
        print(json.dumps({'success': False, 'error': f'Failed to read input file: {str(e)}'}))
        sys.exit(1)
    
    # Initialize predictor and make predictions
    predictor = EmergencyPredictor()
    result = predictor.predict_incident_outcome(incident_data)
    
    # Output result as JSON
    print(json.dumps(result))

if __name__ == '__main__':
    main()

import React, { useState } from 'react';
import {
  Card,
  CardContent,
  Typography,
  Box,
  Collapse,
  IconButton,
  List,
  ListItem,
  ListItemText,
  Chip,
  Button,
} from '@mui/material';
import { ExpandMore as ExpandMoreIcon, ExpandLess as ExpandLessIcon } from '@mui/icons-material';

interface TutorialStep {
  title: string;
  description: string;
  tips?: string[];
}

interface QuickHowToTutorialProps {
  module: string;
  steps: TutorialStep[];
}

const QuickHowToTutorial: React.FC<QuickHowToTutorialProps> = ({ module, steps }) => {
  const [expanded, setExpanded] = useState(false);
  const [currentStep, setCurrentStep] = useState(0);

  const handleToggle = () => {
    setExpanded(!expanded);
  };

  const handleNext = () => {
    if (currentStep < steps.length - 1) {
      setCurrentStep(currentStep + 1);
    }
  };

  const handlePrev = () => {
    if (currentStep > 0) {
      setCurrentStep(currentStep - 1);
    }
  };

  return (
    <Card sx={{ mt: 4, position: 'fixed', bottom: 0, left: 0, right: 0, zIndex: 1000 }}>
      <CardContent sx={{ pb: 1 }}>
        <Box display="flex" justifyContent="space-between" alignItems="center">
          <Typography variant="h6">
            Quick How-To Tutorial: {module}
          </Typography>
          <IconButton onClick={handleToggle}>
            {expanded ? <ExpandLessIcon /> : <ExpandMoreIcon />}
          </IconButton>
        </Box>
        <Collapse in={expanded}>
          <Box sx={{ mt: 2 }}>
            <Box display="flex" alignItems="center" mb={2}>
              <Chip
                label={`Step ${currentStep + 1} of ${steps.length}`}
                color="primary"
                size="small"
              />
              <Typography variant="h6" sx={{ ml: 2 }}>
                {steps[currentStep]?.title}
              </Typography>
            </Box>
            <Typography variant="body1" paragraph>
              {steps[currentStep]?.description}
            </Typography>
            {steps[currentStep]?.tips && (
              <Box>
                <Typography variant="subtitle2" gutterBottom>
                  Tips:
                </Typography>
                <List dense>
                  {steps[currentStep].tips.map((tip, index) => (
                    <ListItem key={index}>
                      <ListItemText primary={`• ${tip}`} />
                    </ListItem>
                  ))}
                </List>
              </Box>
            )}
            <Box display="flex" justifyContent="space-between" mt={2}>
              <Button
                variant="outlined"
                onClick={handlePrev}
                disabled={currentStep === 0}
              >
                Previous
              </Button>
              <Button
                variant="contained"
                onClick={handleNext}
                disabled={currentStep === steps.length - 1}
              >
                Next
              </Button>
            </Box>
          </Box>
        </Collapse>
      </CardContent>
    </Card>
  );
};

export default QuickHowToTutorial;
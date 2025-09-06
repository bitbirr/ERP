import React from 'react';
import { IconButton, Tooltip } from '@mui/material';
import { Help as MuiHelpIcon } from '@mui/icons-material';

interface HelpIconProps {
  title: string;
  placement?: 'top' | 'bottom' | 'left' | 'right';
}

const HelpIcon: React.FC<HelpIconProps> = ({ title, placement = 'top' }) => {
  return (
    <Tooltip title={title} placement={placement} arrow>
      <IconButton size="small" color="primary">
        <MuiHelpIcon fontSize="small" />
      </IconButton>
    </Tooltip>
  );
};

export default HelpIcon;
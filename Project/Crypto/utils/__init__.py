"""
Utilities Package

Contains utility modules for the crypto trading system including
dual-version bridging, performance optimizations, and helper functions.
"""

from .dual_version_bridge import (
    DualVersionBridge, InterfaceType, VersionInfo, SyncState, 
    BridgeMessage, SettingsConverter, StateSync,
    get_dual_version_bridge, initialize_dual_version_bridge,
    shutdown_dual_version_bridge
)

__all__ = [
    'DualVersionBridge',
    'InterfaceType', 
    'VersionInfo',
    'SyncState',
    'BridgeMessage',
    'SettingsConverter',
    'StateSync',
    'get_dual_version_bridge',
    'initialize_dual_version_bridge', 
    'shutdown_dual_version_bridge'
]